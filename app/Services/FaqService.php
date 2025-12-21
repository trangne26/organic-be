<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class FaqService
{
    private const FAQ_FILE_PATH = 'app/faqs.json';

    /**
     * Load all FAQs from JSON file
     *
     * @return array
     */
    public function loadFaqs(): array
    {
        $filePath = storage_path(self::FAQ_FILE_PATH);

        if (!File::exists($filePath)) {
            Log::warning('FAQ file not found', ['path' => $filePath]);
            return [];
        }

        try {
            $content = File::get($filePath);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse FAQ JSON', [
                    'error' => json_last_error_msg(),
                    'path' => $filePath
                ]);
                return [];
            }

            return $data['faqs'] ?? [];
        } catch (\Exception $e) {
            Log::error('Error loading FAQs', [
                'error' => $e->getMessage(),
                'path' => $filePath
            ]);
            return [];
        }
    }

    /**
     * Find FAQ by key
     *
     * @param string $key
     * @return array|null
     */
    public function findFaqByKey(string $key): ?array
    {
        $faqs = $this->loadFaqs();

        foreach ($faqs as $faq) {
            if (isset($faq['key']) && $faq['key'] === $key) {
                return $faq;
            }
        }

        return null;
    }

    /**
     * Find FAQ by keywords in message (fallback method)
     *
     * @param string $message
     * @return array|null
     */
    public function findFaqByKeywords(string $message): ?array
    {
        $faqs = $this->loadFaqs();
        $messageLower = mb_strtolower($message, 'UTF-8');
        $bestMatch = null;
        $maxMatches = 0;

        foreach ($faqs as $faq) {
            if (!isset($faq['keywords']) || !is_array($faq['keywords'])) {
                continue;
            }

            $matchCount = 0;
            foreach ($faq['keywords'] as $keyword) {
                $keywordLower = mb_strtolower($keyword, 'UTF-8');
                if (mb_strpos($messageLower, $keywordLower) !== false) {
                    $matchCount++;
                }
            }

            if ($matchCount > $maxMatches) {
                $maxMatches = $matchCount;
                $bestMatch = $faq;
            }
        }

        // Chỉ trả về nếu có ít nhất 1 keyword match
        return $maxMatches > 0 ? $bestMatch : null;
    }

    /**
     * Get all FAQ keys
     *
     * @return array
     */
    public function getAllKeys(): array
    {
        $faqs = $this->loadFaqs();
        return array_column($faqs, 'key');
    }
}

