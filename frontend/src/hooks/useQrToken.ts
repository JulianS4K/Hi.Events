import {useCallback, useEffect, useRef, useState} from 'react';
import {IdParam} from '../types.ts';
import {attendeeClientPublic} from '../api/attendee.client.ts';

interface QrTokenState {
    token: string | null;
    validUntil: number | null;
    secondsLeft: number;
    isOffline: boolean;
    isLoading: boolean;
}

const WINDOW_SECONDS = 30;
const POLL_INTERVAL  = 25_000;
const TICK_INTERVAL  = 1_000;

export const useQrToken = (
    eventId: IdParam,
    attendeeShortId: string,
    enabled: boolean,
) => {
    const [state, setState] = useState<QrTokenState>({
        token:       null,
        validUntil:  null,
        secondsLeft: WINDOW_SECONDS,
        isOffline:   false,
        isLoading:   true,
    });

    const eventIdRef     = useRef(eventId);
    const shortIdRef     = useRef(attendeeShortId);
    const enabledRef     = useRef(enabled);
    const pollTimer      = useRef<ReturnType<typeof setInterval> | null>(null);
    const countdownTimer = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => { eventIdRef.current  = eventId; },         [eventId]);
    useEffect(() => { shortIdRef.current  = attendeeShortId; }, [attendeeShortId]);
    useEffect(() => { enabledRef.current  = enabled; },         [enabled]);

    const fetchToken = useCallback(async () => {
        if (!enabledRef.current || !eventIdRef.current || !shortIdRef.current) return;
        try {
            const {token, valid_until} = await attendeeClientPublic.getQrToken(
                eventIdRef.current,
                shortIdRef.current,
            );
            setState(s => ({...s, token, validUntil: valid_until, isOffline: false, isLoading: false}));
        } catch {
            setState(s => ({...s, isOffline: true, isLoading: false}));
        }
    }, []);

    const stopPolling = useCallback(() => {
        if (pollTimer.current) {
            clearInterval(pollTimer.current);
            pollTimer.current = null;
        }
    }, []);

    const startPolling = useCallback(() => {
        stopPolling();
        fetchToken();
        pollTimer.current = setInterval(fetchToken, POLL_INTERVAL);
    }, [fetchToken, stopPolling]);

    useEffect(() => {
        if (!enabled) return;

        const onVisibility = () => {
            document.hidden ? stopPolling() : startPolling();
        };

        document.addEventListener('visibilitychange', onVisibility);
        startPolling();

        return () => {
            document.removeEventListener('visibilitychange', onVisibility);
            stopPolling();
        };
    }, [enabled]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (!enabled) return;

        countdownTimer.current = setInterval(() => {
            setState(s => {
                if (!s.validUntil) return s;
                const now         = Math.floor(Date.now() / 1000);
                const secondsLeft = Math.max(0, s.validUntil - now);
                return {...s, secondsLeft};
            });
        }, TICK_INTERVAL);

        return () => {
            if (countdownTimer.current) clearInterval(countdownTimer.current);
        };
    }, [enabled]);

    return state;
};
