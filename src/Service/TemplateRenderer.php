<?php

namespace MarkHitchk\TrafficGuard\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;

class TemplateRenderer
{
    private $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function statusCode(Decision $decision)
    {
        if ($decision->statusCode !== null && $decision->statusCode >= 400 && $decision->statusCode <= 599) {
            return $decision->statusCode;
        }

        $key = $decision->responseKey ?: $decision->category ?: 'default';
        $configured = (int) $this->get('status_'.$key, $this->get('default_status', '403'));

        return ($configured >= 400 && $configured <= 599) ? $configured : 403;
    }

    public function render(Decision $decision, $ip, ServerRequestInterface $request)
    {
        $key = $decision->responseKey ?: $decision->category ?: 'default';
        $html = (string) $this->get('page_'.$key, '');
        if ($html === '') {
            $html = (string) $this->get('page_default', '<h1>Access Restricted</h1>');
        }

        $status = $this->statusCode($decision);
        $threat = $decision->threat;
        $blockId = 'TG-'.strtoupper(substr(hash('sha256', $ip.'|'.$key.'|'.($decision->ruleId ?: 'auto')), 0, 12));
        $uri = $request->getUri();
        $baseUrl = $uri->getScheme().'://'.$uri->getAuthority();

        $vars = [
            'IP' => $ip,
            'STATUS' => (string) $status,
            'REASON' => $decision->reason ?: 'Access to this service has been restricted.',
            'BLOCK_ID' => $blockId,
            'BLOCK_TYPE' => strtoupper((string) ($decision->category ?: 'BLOCK')),
            'RULE_ID' => $decision->ruleId !== null ? (string) $decision->ruleId : '',
            'EXPIRES' => $decision->expiresAt ?: 'Permanent / not specified',
            'DATE_UTC' => gmdate('Y-m-d H:i:s').' UTC',
            'PATH' => $uri->getPath(),
            'COUNTRY' => isset($threat['country']) ? (string) $threat['country'] : '',
            'COUNTRY_CODE' => isset($threat['country_code']) ? (string) $threat['country_code'] : '',
            'ASN' => isset($threat['asn']) ? (string) $threat['asn'] : '',
            'RISK' => isset($threat['risk']) && $threat['risk'] !== null ? (string) $threat['risk'] : '',
            'FORUM_NAME' => (string) $this->settings->get('forum_title', 'Flarum'),
            'FORUM_URL' => $baseUrl,
            'SUPPORT_URL' => (string) $this->get('support_url', ''),
        ];

        foreach ($vars as $name => $value) {
            $html = str_replace('{{'.$name.'}}', htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
        }

        return $html;
    }

    private function get($name, $default = null)
    {
        return $this->settings->get('markhitchk-traffic-guard.'.$name, $default);
    }
}
