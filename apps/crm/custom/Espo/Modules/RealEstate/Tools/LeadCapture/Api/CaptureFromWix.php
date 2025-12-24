<?php

namespace Espo\Modules\RealEstate\Tools\LeadCapture\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Tools\LeadCapture\CaptureService;
use stdClass;

/**
 * Custom LeadCapture endpoint for Wix Automations.
 * Wix wraps payload in {"data": {...}}, this unwraps it.
 *
 * URL: POST /api/v1/LeadCaptureWix/:apiKey
 * Example: POST https://crm.dreamhold.org/api/v1/LeadCaptureWix/1a77ddba16d4a7aff788795c5fc076ef
 */
class CaptureFromWix implements Action
{
    public function __construct(
        private CaptureService $captureService
    ) {}

    public function process(Request $request): Response
    {
        $apiKey = $request->getRouteParam('apiKey');

        if (!$apiKey) {
            throw new BadRequest('No API key provided.');
        }

        $body = $request->getParsedBody();

        // Unwrap Wix's {"data": {...}} structure
        $data = $body->data ?? $body;

        if (!$data instanceof stdClass) {
            $data = (object) $data;
        }

        // Call standard LeadCapture service
        $this->captureService->capture($apiKey, $data);

        return ResponseComposer::json([
            'success' => true,
        ]);
    }
}
