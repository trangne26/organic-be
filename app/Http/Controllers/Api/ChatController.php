<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\AiService;
use App\Services\FaqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private FaqService $faqService;
    private AiService $aiService;

    public function __construct(FaqService $faqService, AiService $aiService)
    {
        $this->faqService = $faqService;
        $this->aiService = $aiService;
    }

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $message = trim($request->input('message'));

        if (empty($message)) {
            return response()->json([
                'success' => false,
                'type' => 'error',
                'reply' => 'Xin lỗi, bạn vui lòng nhập câu hỏi.',
            ], 400);
        }

        try {
            $intentData = $this->detectIntent($message);

            if (!isset($intentData['intent']) || !in_array($intentData['intent'], ['faq', 'product_search'])) {
                $intentData = [
                    'intent' => 'product_search',
                    'keywords' => [$message],
                ];
            }

            if ($intentData['intent'] === 'faq') {
                return $this->handleFaq($message, $intentData);
            } else {
                return $this->handleProductSearch($message, $intentData);
            }
        } catch (\Exception $e) {
            \Log::error('Chat error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            try {
                return $this->handleProductSearch($message, [
                    'intent' => 'product_search',
                    'keywords' => [$message],
                ]);
            } catch (\Exception $e2) {
                return response()->json([
                    'success' => false,
                    'type' => 'error',
                    'reply' => 'Xin lỗi, tôi không thể xử lý câu hỏi này. Vui lòng thử lại hoặc liên hệ với chúng tôi.',
                ], 500);
            }
        }
    }

    private function detectIntent(string $message): array
    {
        $intentData = $this->aiService->detectIntent($message);
        
        if ($intentData === null) {
            return $this->guessIntentFromKeywords($message);
        }
        
        return $intentData;
    }

    private function handleFaq(string $message, array $intentData): JsonResponse
    {
        $faqKey = $intentData['faq_key'] ?? null;
        $faq = null;

        if ($faqKey) {
            $faq = $this->faqService->findFaqByKey($faqKey);
        }

        if (!$faq) {
            $faq = $this->faqService->findFaqByKeywords($message);
        }

        if ($faq) {
            $reply = $this->generateFaqResponse($message, $faq);
            
            return response()->json([
                'success' => true,
                'type' => 'faq',
                'reply' => $reply,
                'faq' => [
                    'key' => $faq['key'],
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                ],
            ]);
        }

        return $this->handleProductSearch($message, [
            'intent' => 'product_search',
            'keywords' => [$message],
        ]);
    }

    private function handleProductSearch(string $message, array $intentData): JsonResponse
    {
        $keywords = $intentData['keywords'] ?? [];
        if (empty($keywords)) {
            $keywords = array_filter(explode(' ', $message), function($word) {
                return mb_strlen(trim($word)) > 1;
            });
            $keywords = array_values($keywords);
        }
        
        $categoryId = $intentData['category_id'] ?? null;
        $minPrice = $intentData['price_min'] ?? null;
        $maxPrice = $intentData['price_max'] ?? null;

        $query = Product::query()->where('is_active', true);

        if (!empty($keywords)) {
            $query->where(function($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $trimmedKeyword = trim($keyword);
                    if (!empty($trimmedKeyword)) {
                        $q->where(function($subQ) use ($trimmedKeyword) {
                            $subQ->where('name', 'like', '%' . $trimmedKeyword . '%')
                                 ->orWhere('description', 'like', '%' . $trimmedKeyword . '%');
                        });
                    }
                }
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        $products = $query->with(['category', 'images'])
            ->limit(10)
            ->get();

        $productsData = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'description' => $product->description,
                'category' => $product->category->name ?? null,
            ];
        })->toArray();

        $reply = $this->generateProductSearchResponse($message, $productsData, $keywords);

        return response()->json([
            'success' => true,
            'type' => 'product_search',
            'reply' => $reply,
            'products' => ProductResource::collection($products),
        ]);
    }

    private function generateFaqResponse(string $message, array $faq): string
    {
        return $this->aiService->generateFaqResponse($message, $faq);
    }

    private function generateProductSearchResponse(string $message, array $productsData, array $keywords): string
    {
        return $this->aiService->generateProductSearchResponse($message, $productsData, $keywords);
    }

    private function guessIntentFromKeywords(string $message): array
    {
        $messageLower = mb_strtolower($message, 'UTF-8');

        $faqKeywords = [
            'giao hàng', 'thời gian', 'bao lâu', 'shipping', 'delivery',
            'thanh toán', 'payment', 'cod', 'chuyển khoản',
            'đổi trả', 'return', 'refund', 'hoàn tiền',
            'giờ mở cửa', 'mở cửa', 'store hours',
            'chứng nhận', 'hữu cơ', 'organic', 'certification',
            'phí vận chuyển', 'phí ship', 'shipping fee',
            'chất lượng', 'quality',
            'liên hệ', 'contact', 'hotline', 'email', 'địa chỉ',
        ];

        foreach ($faqKeywords as $keyword) {
            if (mb_strpos($messageLower, mb_strtolower($keyword, 'UTF-8')) !== false) {
                $faq = $this->faqService->findFaqByKeywords($message);
                if ($faq) {
                    return [
                        'intent' => 'faq',
                        'faq_key' => $faq['key'],
                    ];
                }
            }
        }

        $keywords = array_filter(explode(' ', $message), function($word) {
            return mb_strlen(trim($word)) > 1;
        });

        return [
            'intent' => 'product_search',
            'keywords' => array_values($keywords),
            'price_min' => null,
            'price_max' => null,
        ];
    }
}

