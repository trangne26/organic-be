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

    /**
     * Main chat endpoint
     *
     * @param Request $request
     * @return JsonResponse
     */
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
            // Step 1: Detect intent (with fallback)
            $intentData = $this->detectIntent($message);

            // Validate intent data
            if (!isset($intentData['intent']) || !in_array($intentData['intent'], ['faq', 'product_search'])) {
                // Invalid intent, default to product search
                $intentData = [
                    'intent' => 'product_search',
                    'keywords' => [$message],
                ];
            }

            // Step 2: Handle based on intent
            if ($intentData['intent'] === 'faq') {
                return $this->handleFaq($message, $intentData);
            } else {
                return $this->handleProductSearch($message, $intentData);
            }
        } catch (\Exception $e) {
            // Log error for debugging
            \Log::error('Chat error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Final fallback: Try to handle as product search
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

    /**
     * Detect intent from user message
     *
     * @param string $message
     * @return array
     */
    private function detectIntent(string $message): array
    {
        // Try AI service first
        $intentData = $this->aiService->detectIntent($message);
        
        // Fallback to keyword matching if AI fails
        if ($intentData === null) {
            return $this->guessIntentFromKeywords($message);
        }
        
        return $intentData;
    }

    /**
     * Handle FAQ intent
     *
     * @param string $message
     * @param array $intentData
     * @return JsonResponse
     */
    private function handleFaq(string $message, array $intentData): JsonResponse
    {
        $faqKey = $intentData['faq_key'] ?? null;
        $faq = null;

        // Try to find FAQ by key
        if ($faqKey) {
            $faq = $this->faqService->findFaqByKey($faqKey);
        }

        // Fallback: Find FAQ by keywords
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

        // No FAQ found, fallback to product search
        return $this->handleProductSearch($message, [
            'intent' => 'product_search',
            'keywords' => [$message],
        ]);
    }

    /**
     * Handle product search intent
     *
     * @param string $message
     * @param array $intentData
     * @return JsonResponse
     */
    private function handleProductSearch(string $message, array $intentData): JsonResponse
    {
        // Extract keywords - fallback to message if empty
        $keywords = $intentData['keywords'] ?? [];
        if (empty($keywords)) {
            // Fallback: Extract keywords from message
            $keywords = array_filter(explode(' ', $message), function($word) {
                return mb_strlen(trim($word)) > 1;
            });
            $keywords = array_values($keywords);
        }
        
        $categoryId = $intentData['category_id'] ?? null;
        $minPrice = $intentData['price_min'] ?? null;
        $maxPrice = $intentData['price_max'] ?? null;

        // Build query
        $query = Product::query()->where('is_active', true);

        // Search by keywords
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

        // Filter by category
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Filter by price
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        // Get products (limit to 10 for chatbot)
        $products = $query->with(['category', 'images'])
            ->limit(10)
            ->get();

        // Prepare products data for AI
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

    /**
     * Generate FAQ response using AI (with fallback)
     *
     * @param string $message
     * @param array $faq
     * @return string
     */
    private function generateFaqResponse(string $message, array $faq): string
    {
        return $this->aiService->generateFaqResponse($message, $faq);
    }

    /**
     * Generate product search response using AI (with fallback)
     *
     * @param string $message
     * @param array $productsData
     * @param array $keywords
     * @return string
     */
    private function generateProductSearchResponse(string $message, array $productsData, array $keywords): string
    {
        return $this->aiService->generateProductSearchResponse($message, $productsData, $keywords);
    }

    /**
     * Fallback: Guess intent from keywords
     *
     * @param string $message
     * @return array
     */
    private function guessIntentFromKeywords(string $message): array
    {
        $messageLower = mb_strtolower($message, 'UTF-8');

        // FAQ keywords
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

        // Check if message contains FAQ keywords
        foreach ($faqKeywords as $keyword) {
            if (mb_strpos($messageLower, mb_strtolower($keyword, 'UTF-8')) !== false) {
                // Try to find matching FAQ
                $faq = $this->faqService->findFaqByKeywords($message);
                if ($faq) {
                    return [
                        'intent' => 'faq',
                        'faq_key' => $faq['key'],
                    ];
                }
            }
        }

        // Default to product search
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

