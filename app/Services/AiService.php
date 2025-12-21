<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL = 'gpt-3.5-turbo';
    private const MAX_TOKENS = 500;
    private const TIMEOUT = 30;

    /**
     * Detect intent from user message
     *
     * @param string $message
     * @return array|null Returns null if AI call fails
     */
    public function detectIntent(string $message): ?array
    {
        $apiKey = config('services.openai.api_key');
        
        if (empty($apiKey)) {
            Log::warning('OpenAI API key not configured');
            return null;
        }

        $prompt = $this->buildIntentDetectionPrompt($message);

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::OPENAI_API_URL, [
                    'model' => self::MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an intent detection assistant. You must respond with valid JSON only, no additional text.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 200,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                
                if ($content) {
                    $intentData = json_decode($content, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && $this->validateIntentData($intentData)) {
                        return $intentData;
                    }
                }
            } else {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('OpenAI API exception', [
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Generate natural response for FAQ
     *
     * @param string $userMessage
     * @param array $faq
     * @return string
     */
    public function generateFaqResponse(string $userMessage, array $faq): string
    {
        $apiKey = config('services.openai.api_key');
        
        if (empty($apiKey)) {
            return $faq['answer'] ?? 'Xin lỗi, tôi không thể trả lời câu hỏi này.';
        }

        $prompt = $this->buildFaqResponsePrompt($userMessage, $faq);

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::OPENAI_API_URL, [
                    'model' => self::MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful customer service assistant for an organic food store. Respond naturally and friendly in Vietnamese.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => self::MAX_TOKENS,
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                
                if ($content) {
                    return trim($content);
                }
            }
        } catch (\Exception $e) {
            Log::error('OpenAI API exception in generateFaqResponse', [
                'message' => $e->getMessage(),
            ]);
        }

        // Fallback to FAQ answer
        return $faq['answer'] ?? 'Xin lỗi, tôi không thể trả lời câu hỏi này.';
    }

    /**
     * Generate natural response for product search
     *
     * @param string $userMessage
     * @param array $products Array of product data
     * @param array $keywords Search keywords used
     * @return string
     */
    public function generateProductSearchResponse(string $userMessage, array $products, array $keywords = []): string
    {
        $apiKey = config('services.openai.api_key');
        
        if (empty($apiKey)) {
            return $this->generateFallbackProductResponse($products);
        }

        $prompt = $this->buildProductSearchResponsePrompt($userMessage, $products, $keywords);

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::OPENAI_API_URL, [
                    'model' => self::MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful customer service assistant for an organic food store. Help customers find products. Respond naturally and friendly in Vietnamese. Only mention products that are provided in the data.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => self::MAX_TOKENS,
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                
                if ($content) {
                    return trim($content);
                }
            }
        } catch (\Exception $e) {
            Log::error('OpenAI API exception in generateProductSearchResponse', [
                'message' => $e->getMessage(),
            ]);
        }

        // Fallback to template response
        return $this->generateFallbackProductResponse($products);
    }

    /**
     * Build prompt for intent detection
     *
     * @param string $message
     * @return string
     */
    private function buildIntentDetectionPrompt(string $message): string
    {
        $faqKeys = [
            'shipping_time', 'payment_methods', 'return_policy', 
            'store_hours', 'organic_certification', 'shipping_fee',
            'product_quality', 'contact_info'
        ];

        return "Phân tích câu hỏi của khách hàng và trả về JSON với format sau:

Nếu là câu hỏi về FAQ (giao hàng, thanh toán, đổi trả, giờ mở cửa, chứng nhận, phí vận chuyển, chất lượng, liên hệ):
{
  \"intent\": \"faq\",
  \"faq_key\": \"shipping_time\" (một trong các key: " . implode(', ', $faqKeys) . ")
}

Nếu là câu hỏi tìm kiếm sản phẩm:
{
  \"intent\": \"product_search\",
  \"keywords\": [\"từ khóa 1\", \"từ khóa 2\"],
  \"category_id\": null (hoặc số nếu có),
  \"price_min\": null (hoặc số nếu có),
  \"price_max\": null (hoặc số nếu có)
}

Câu hỏi của khách hàng: \"{$message}\"

Trả về JSON hợp lệ, không có text thêm:";
    }

    /**
     * Build prompt for FAQ response generation
     *
     * @param string $userMessage
     * @param array $faq
     * @return string
     */
    private function buildFaqResponsePrompt(string $userMessage, array $faq): string
    {
        return "Khách hàng hỏi: \"{$userMessage}\"

Thông tin FAQ:
- Câu hỏi: {$faq['question']}
- Câu trả lời: {$faq['answer']}

Hãy viết lại câu trả lời một cách tự nhiên, thân thiện, bằng tiếng Việt. Bạn chỉ được sử dụng thông tin trong FAQ, không được thêm thông tin khác. Bắt đầu trả lời trực tiếp, không cần lặp lại câu hỏi.";
    }

    /**
     * Build prompt for product search response generation
     *
     * @param string $userMessage
     * @param array $products
     * @param array $keywords
     * @return string
     */
    private function buildProductSearchResponsePrompt(string $userMessage, array $products, array $keywords): string
    {
        $productsJson = json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $keywordsStr = !empty($keywords) ? implode(', ', $keywords) : 'không có';

        return "Khách hàng hỏi: \"{$userMessage}\"

Từ khóa tìm kiếm: {$keywordsStr}

Danh sách sản phẩm tìm được:
{$productsJson}

Hãy viết một câu trả lời tự nhiên, thân thiện bằng tiếng Việt để giới thiệu các sản phẩm này cho khách hàng. Chỉ được đề cập đến các sản phẩm trong danh sách trên, không được thêm sản phẩm khác. Nếu có nhiều sản phẩm, hãy giới thiệu một vài sản phẩm nổi bật. Bắt đầu trả lời trực tiếp, không cần lặp lại câu hỏi.";
    }

    /**
     * Validate intent data structure
     *
     * @param array|null $data
     * @return bool
     */
    private function validateIntentData(?array $data): bool
    {
        if (!is_array($data) || !isset($data['intent'])) {
            return false;
        }

        $intent = $data['intent'];

        if ($intent === 'faq') {
            return isset($data['faq_key']) && is_string($data['faq_key']);
        }

        if ($intent === 'product_search') {
            return isset($data['keywords']) && is_array($data['keywords']);
        }

        return false;
    }

    /**
     * Generate fallback product response
     *
     * @param array $products
     * @return string
     */
    private function generateFallbackProductResponse(array $products): string
    {
        $count = count($products);

        if ($count === 0) {
            return 'Xin lỗi, hiện tại chúng tôi chưa có sản phẩm phù hợp với yêu cầu của bạn. Bạn có thể thử tìm kiếm với từ khóa khác hoặc liên hệ với chúng tôi để được tư vấn.';
        }

        $productNames = array_slice(array_column($products, 'name'), 0, 3);
        $namesStr = implode(', ', $productNames);

        if ($count === 1) {
            return "Tôi tìm thấy 1 sản phẩm phù hợp: {$namesStr}. Bạn có thể xem chi tiết sản phẩm ở bên dưới.";
        } else {
            return "Tôi tìm thấy {$count} sản phẩm phù hợp, ví dụ: {$namesStr}. Bạn có thể xem danh sách đầy đủ ở bên dưới.";
        }
    }
}

