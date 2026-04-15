import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {TransferTicketData, selfServiceClient} from "../api/self-service.client.ts";

export const useTransferTicket = () => {
    return useMutation({
        mutationFn: ({eventId, orderShortId, attendeeShortId, data}: {
            eventId: IdParam;
            orderShortId: string;
            attendeeShortId: string;
            data: TransferTicketData;
        }) => selfServiceClient.transferTicket(eventId, orderShortId, attendeeShortId, data)
    });
};
