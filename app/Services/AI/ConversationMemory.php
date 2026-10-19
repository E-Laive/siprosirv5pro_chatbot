<?php
namespace App\Services\AI;

/** Compact, deterministic memory. Report rows, SQL, credentials and POS identities are never summarized. */
final class ConversationMemory
{
    public function build(array $messages, array $context): array
    {
        $memory = ['version' => 1, 'response_style' => null, 'reports' => []];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'user') {
                if (preg_match('/\b(?:jawab|jawaban|jelaskan|penjelasan)\b.*\b(ringkas|singkat|detail|rinci)\b/iu', $m['content'] ?? '', $match)) $memory['response_style'] = in_array(mb_strtolower($match[1]), ['ringkas','singkat'], true) ? 'concise' : 'detailed';
                continue;
            }
            $meta = $m['metadata'] ?? [];
            if (($meta['response_kind'] ?? '') !== 'analysis' || ($meta['intent'] ?? '') === 'reformat_previous' || empty($meta['report_ref'])) continue;
            $memory['reports'][] = ['report_ref' => $meta['report_ref'], 'domain' => $meta['domain'], 'intent' => $meta['intent'], 'period' => array_intersect_key($meta['report_scope'] ?? [], array_flip(['year','month','date_start','date_end','comparison','group_by']))];
        }
        $memory['reports'] = array_slice($memory['reports'], -8);
        $memory['pending_slot'] = $context['pending_request']['slot'] ?? null;
        return $memory;
    }

    public function forPrompt(array $memory, array $allowedDomains): array
    {
        $memory = array_intersect_key($memory, array_flip(['version','response_style','reports','pending_slot']));
        $memory['reports'] = array_values(array_filter($memory['reports'] ?? [], static fn ($r) => in_array($r['domain'], $allowedDomains, true)));
        return $memory;
    }

    /** Select a small relevant older exchange in addition to the recent window. Never truncate away recent messages. */
    public function retrieve(array $eligible, int $limit, string $question): array
    {
        $recent = array_slice($eligible, -$limit);
        $older = array_slice($eligible, 0, max(0, count($eligible) - $limit));
        if ($older === [] || $question === '') return $recent;
        $words = array_values(array_filter(preg_split('/[^\pL\pN]+/u', mb_strtolower($question)), static fn ($w) => mb_strlen($w) >= 4 && !in_array($w, ['tadi','yang','tolong','untuk','dengan','saya','kamu','jelaskan','buatkan','laporan','tersebut','sebelumnya'], true)));
        $best = null; $bestScore = 0;
        foreach ($older as $i => $m) {
            if ($m['role'] !== 'user') continue;
            $text = mb_strtolower($m['content']); $score = 0;
            foreach ($words as $w) if (str_contains($text, $w)) $score++;
            if ($score >= $bestScore && $score > 0) { $best = $i; $bestScore = $score; }
        }
        if ($best === null) return $recent;
        $found = [$older[$best]];
        if (($older[$best + 1]['role'] ?? '') === 'assistant') $found[] = $older[$best + 1];
        return array_merge($found, $recent);
    }
}
