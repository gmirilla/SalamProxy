<?php

namespace App\Http\Controllers;

class EliteUpdateLogController extends Controller
{
    /**
     * GET /elite/updates
     * Shows the most recent entries from storage/logs/eliteupdate.log,
     * newest first. Protected by HTTP Basic Auth (auditlog.auth middleware).
     */
    public function index()
    {
        $path = storage_path('logs/eliteupdate.log');
        $entries = [];

        if (is_file($path)) {
            $lines = array_filter(explode("\n", file_get_contents($path)));

            foreach (array_reverse($lines) as $line) {
                if (count($entries) >= 200) {
                    break;
                }

                if (!preg_match('/^\[(?<ts>[^\]]+)\]\s+\S+\.\S+:\s+(?<message>[^{]+)(?<context>\{.*\})?\s*$/', trim($line), $m)) {
                    continue;
                }

                $context = isset($m['context']) && $m['context'] !== ''
                    ? json_decode($m['context'], true) ?? []
                    : [];

                $entries[] = [
                    'timestamp' => $m['ts'],
                    'message'   => trim($m['message']),
                    'ip'        => $context['ip'] ?? null,
                    'policy_no' => $context['policy_no'] ?? null,
                    'item_id'   => $context['item_id'] ?? null,
                    'old_value' => $context['old_value'] ?? null,
                    'new_value' => $context['new_value'] ?? null,
                    'result'    => $context['result'] ?? null,
                    'error'     => $context['error'] ?? null,
                ];
            }
        }

        return view('elite-update-log', ['entries' => $entries]);
    }
}
