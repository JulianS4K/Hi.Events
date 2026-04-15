import {useQuery} from "@tanstack/react-query";
import {IdParam, TicketTransfer} from "../types.ts";
import {attendeesClient} from "../api/attendee.client.ts";

export const GET_ATTENDEE_TRANSFERS_QUERY_KEY = 'getAttendeeTransfers';

export const useGetAttendeeTransfers = (eventId: IdParam, attendeeId: IdParam) => {
    return useQuery<TicketTransfer[]>({
        queryKey: [GET_ATTENDEE_TRANSFERS_QUERY_KEY, eventId, attendeeId],
        queryFn: async () => {
            const response = await attendeesClient.getTransfers(eventId, attendeeId);
            return response.data;
        },
        staleTime: 0,
        gcTime: 0,
    });
};
