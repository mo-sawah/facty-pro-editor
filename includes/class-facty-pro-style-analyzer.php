<?php
/**
 * Facty Pro Style Analyzer
 * Analyzes writing style, readability, and provides suggestions for improvement
 */

if (!defined('ABSPATH')) {
    exit;
}

class Facty_Pro_Style_Analyzer {
    
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
    }
    
    /**
     * Analyze content style and readability
     */
    public function analyze($content, $job_id = null) {
        $clean_content = wp_strip_all_tags($content);
        
        $issues = array();
        $suggestions = array();
        $readability_score = 100;
        
        // Calculate readability metrics
        $metrics = $this->calculate_readability_metrics($clean_content);
        
        // Analyze based on metrics
        $flesch_analysis = $this->analyze_flesch_score($metrics['flesch_score']);
        $issues = array_merge($issues, $flesch_analysis['issues']);
        $suggestions = array_merge($suggestions, $flesch_analysis['suggestions']);
        $readability_score -= $flesch_analysis['penalty'];
        
        // Analyze sentence length
        $sentence_analysis = $this->analyze_sentence_length($metrics);
        $issues = array_merge($issues, $sentence_analysis['issues']);
        $suggestions = array_merge($suggestions, $sentence_analysis['suggestions']);
        $readability_score -= $sentence_analysis['penalty'];
        
        // Analyze passive voice
        $passive_analysis = $this->analyze_passive_voice($clean_content);
        $issues = array_merge($issues, $passive_analysis['issues']);
        $suggestions = array_merge($suggestions, $passive_analysis['suggestions']);
        $readability_score -= $passive_analysis['penalty'];
        
        // Analyze adverbs
        $adverb_analysis = $this->analyze_adverbs($clean_content);
        $issues = array_merge($issues, $adverb_analysis['issues']);
        $suggestions = array_merge($suggestions, $adverb_analysis['suggestions']);
        $readability_score -= $adverb_analysis['penalty'];
        
        // Analyze complex words
        $complex_analysis = $this->analyze_complex_words($clean_content);
        $issues = array_merge($issues, $complex_analysis['issues']);
        $suggestions = array_merge($suggestions, $complex_analysis['suggestions']);
        $readability_score -= $complex_analysis['penalty'];
        
        // Ensure score doesn't go below 0
        $readability_score = max(0, $readability_score);
        
        return array(
            'readability_score' => $readability_score,
            'metrics' => $metrics,
            'issues' => $issues,
            'suggestions' => $suggestions,
            'analyzed_at' => current_time('mysql')
        );
    }
    
    /**
     * Calculate readability metrics
     */
    private function calculate_readability_metrics($text) {
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        $words = str_word_count($text, 1);
        $word_count = count($words);
        
        $syllable_count = 0;
        foreach ($words as $word) {
            $syllable_count += $this->count_syllables($word);
        }
        
        // Flesch Reading Ease Score
        // Formula: 206.835 - 1.015 * (words/sentences) - 84.6 * (syllables/words)
        if ($sentence_count > 0 && $word_count > 0) {
            $flesch_score = 206.835 - (1.015 * ($word_count / $sentence_count)) - (84.6 * ($syllable_count / $word_count));
            $flesch_score = max(0, min(100, $flesch_score)); // Clamp between 0-100
        } else {
            $flesch_score = 0;
        }
        
        // Average sentence length
        $avg_sentence_length = $sentence_count > 0 ? $word_count / $sentence_count : 0;
        
        // Average syllables per word
        $avg_syllables_per_word = $word_count > 0 ? $syllable_count / $word_count : 0;
        
        return array(
            'word_count' => $word_count,
            'sentence_count' => $sentence_count,
            'syllable_count' => $syllable_count,
            'flesch_score' => round($flesch_score, 1),
            'avg_sentence_length' => round($avg_sentence_length, 1),
            'avg_syllables_per_word' => round($avg_syllables_per_word, 2)
        );
    }
    
    /**
     * Count syllables in a word (simple approximation)
     */
    private function count_syllables($word) {
        $word = strtolower($word);
        $syllables = 0;
        $vowels = array('a', 'e', 'i', 'o', 'u', 'y');
        $previous_was_vowel = false;
        
        for ($i = 0; $i < strlen($word); $i++) {
            $is_vowel = in_array($word[$i], $vowels);
            if ($is_vowel && !$previous_was_vowel) {
                $syllables++;
            }
            $previous_was_vowel = $is_vowel;
        }
        
        // Adjust for silent e
        if (strlen($word) > 2 && substr($word, -1) === 'e') {
            $syllables--;
        }
        
        return max(1, $syllables);
    }
    
    /**
     * Analyze Flesch Reading Ease Score
     */
    private function analyze_flesch_score($score) {
        $issues = array();
        $suggestions = array();
        $penalty = 0;
        
        if ($score < 30) {
            $issues[] = array(
                'type' => 'error',
                'message' => 'Very difficult to read (Flesch score: ' . $score . ')',
                'severity' => 'high'
            );
            $suggestions[] = 'Your content is very difficult to read. Simplify your language, use shorter sentences, and break down complex ideas.';
            $penalty = 30;
        } elseif ($score < 50) {
            $issues[] = array(
                'type' => 'warning',
                'message' => 'Difficult to read (Flesch score: ' . $score . ')',
                'severity' => 'medium'
            );
            $suggestions[] = 'Your content is fairly difficult to read. Consider simplifying sentences and using more common words.';
            $penalty = 20;
        } elseif ($score < 60) {
            $issues[] = array(
                'type' => 'info',
                'message' => 'Moderately difficult to read (Flesch score: ' . $score . ')',
                'severity' => 'low'
            );
            $suggestions[] = 'Your content is moderately readable. For broader appeal, consider simplifying some complex sentences.';
            $penalty = 10;
        } elseif ($score > 90) {
            $suggestions[] = 'Excellent readability! Your content is very easy to read and accessible to most audiences.';
        }
        
        return array(
            'issues' => $issues,
            'suggestions' => $suggestions,
            'penalty' => $penalty
        );
    }
    
    /**
     * Analyze average sentence length
     */
    private function analyze_sentence_length($metrics) {
        $issues = array();
        $suggestions = array();
        $penalty = 0;
        $avg_length = $metrics['avg_sentence_length'];
        
        if ($avg_length > 25) {
            $issues[] = array(
                'type' => 'warning',
                'message' => 'Sentences are too long on average (' . round($avg_length) . ' words)',
                'severity' => 'medium'
            );
            $suggestions[] = 'Your sentences are quite long. Aim for 15-20 words per sentence. Break long sentences into shorter ones.';
            $penalty = 15;
        } elseif ($avg_length > 20) {
            $suggestions[] = 'Consider shortening some sentences. Aim for an average of 15-20 words per sentence for optimal readability.';
            $penalty = 5;
        }
        
        return array(
            'issues' => $issues,
            'suggestions' => $suggestions,
            'penalty' => $penalty
        );
    }
    
    /**
     * Analyze passive voice usage
     */
    private function analyze_passive_voice($text) {
        $issues = array();
        $suggestions = array();
        $penalty = 0;
        
        // Simple passive voice detection patterns
        $passive_patterns = array(
            '/\b(is|are|was|were|be|been|being)\s+\w+ed\b/i',
            '/\b(is|are|was|were|be|been|being)\s+\w+en\b/i'
        );
        
        $passive_count = 0;
        foreach ($passive_patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            $passive_count += count($matches[0]);
        }
        
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        if ($sentence_count > 0) {
            $passive_percentage = ($passive_count / $sentence_count) * 100;
            
            if ($passive_percentage > 15) {
                $issues[] = array(
                    'type' => 'warning',
                    'message' => 'High passive voice usage (~' . round($passive_percentage) . '%)',
                    'severity' => 'medium'
                );
                $suggestions[] = 'You have a high percentage of passive voice. Convert passive sentences to active voice for stronger, clearer writing.';
                $penalty = 12;
            } elseif ($passive_percentage > 10) {
                $suggestions[] = 'Consider reducing passive voice usage. Active voice is typically stronger and more engaging.';
                $penalty = 5;
            }
        }
        
        return array(
            'issues' => $issues,
            'suggestions' => $suggestions,
            'penalty' => $penalty
        );
    }
    
    /**
     * Analyze adverb usage
     */
    private function analyze_adverbs($text) {
        $issues = array();
        $suggestions = array();
        $penalty = 0;
        
        // Common adverb patterns
        preg_match_all('/\b\w+ly\b/i', $text, $matches);
        $adverb_count = count($matches[0]);
        
        $words = str_word_count($text);
        
        if ($words > 0) {
            $adverb_percentage = ($adverb_count / $words) * 100;
            
            if ($adverb_percentage > 5) {
                $issues[] = array(
                    'type' => 'info',
                    'message' => 'High adverb usage (~' . round($adverb_percentage, 1) . '%)',
                    'severity' => 'low'
                );
                $suggestions[] = 'You use a lot of adverbs (-ly words). Consider replacing them with stronger verbs for more impactful writing.';
                $penalty = 8;
            }
        }
        
        return array(
            'issues' => $issues,
            'suggestions' => $suggestions,
            'penalty' => $penalty
        );
    }
    
    /**
     * Analyze complex word usage
     */
    private function analyze_complex_words($text) {
        $issues = array();
        $suggestions = array();
        $penalty = 0;
        
        $words = str_word_count($text, 1);
        $complex_count = 0;
        
        foreach ($words as $word) {
            // Consider words with 3+ syllables as complex
            if ($this->count_syllables($word) >= 3) {
                $complex_count++;
            }
        }
        
        $total_words = count($words);
        
        if ($total_words > 0) {
            $complex_percentage = ($complex_count / $total_words) * 100;
            
            if ($complex_percentage > 20) {
                $issues[] = array(
                    'type' => 'warning',
                    'message' => 'High use of complex words (~' . round($complex_percentage) . '%)',
                    'severity' => 'medium'
                );
                $suggestions[] = 'You use many complex words (3+ syllables). Simplify where possible to improve readability.';
                $penalty = 10;
            }
        }
        
        return array(
            'issues' => $issues,
            'suggestions' => $suggestions,
            'penalty' => $penalty
        );
    }
}
