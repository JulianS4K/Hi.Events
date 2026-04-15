<?php

namespace HiEvents\Http\Actions\TicketTransfer;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\TicketTransfer\TransferTicketPublicRequest;
use HiEvents\Services\Application\Handlers\TicketTransfer\DTO\TransferTicketDTO;
use HiEvents\Services\Application\Handlers\TicketTransfer\TransferTicketHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class TransferTicketPublicAction extends BaseAction
{
    public function __construct(
        private readonly TransferTicketHandler $handler,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(
        TransferTicketPublicRequest $request,
        int                         $eventId,
        string                      $orderShortId,
        string                      $attendeeShortId,
    ): JsonResponse {
        try {
            $this->handler->handle(new TransferTicketDTO(
                eventId:          $eventId,
                orderShortId:     $orderShortId,
                attendeeShortId:  $attendeeShortId,
                toIdentifier:     $request->input('to_identifier'),
                toIdentifierType: $request->input('to_identifier_type'),
                ipAddress:        $this->getClientIp($request),
                userAgent:        $request->userAgent(),
            ));

            return $this->jsonResponse([
                'message' => __('Ticket transferred successfully.'),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}
