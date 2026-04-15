import {t} from "@lingui/macro";
import {IconAt, IconPhone, IconSwitch2} from "@tabler/icons-react";
import {useParams} from "react-router";
import {IdParam} from "../../../types.ts";
import {useGetAttendeeTransfers} from "../../../queries/useGetAttendeeTransfers.ts";
import {relativeDate} from "../../../utilites/dates.ts";
import {LoadingMask} from "../LoadingMask";
import classes from "./AttendeeTransferHistory.module.scss";

interface AttendeeTransferHistoryProps {
    attendeeId: IdParam;
}

export const AttendeeTransferHistory = ({attendeeId}: AttendeeTransferHistoryProps) => {
    const {eventId} = useParams();
    const {data: transfers, isLoading} = useGetAttendeeTransfers(eventId, attendeeId);

    if (isLoading) {
        return <LoadingMask/>;
    }

    if (!transfers || transfers.length === 0) {
        return (
            <div className={classes.empty}>
                {t`No transfers for this ticket.`}
            </div>
        );
    }

    return (
        <div className={classes.timeline}>
            {transfers.map((transfer) => {
                const isPhone = transfer.to_identifier_type === 'phone';
                const maskedTo = isPhone
                    ? t`Phone ···` + transfer.to_identifier.slice(-4)
                    : transfer.to_identifier;

                return (
                    <div key={transfer.id} className={classes.row}>
                        <div className={classes.iconCol}>
                            <IconSwitch2 size={16}/>
                        </div>
                        <div className={classes.body}>
                            <div className={classes.route}>
                                <span>{transfer.from_email}</span>
                                <span className={classes.arrow}>→</span>
                                <span>{maskedTo}</span>
                                <span
                                    className={classes.badge}
                                    data-type={transfer.to_identifier_type}
                                >
                                    {isPhone
                                        ? <><IconPhone size={10}/> {t`Phone`}</>
                                        : <><IconAt size={10}/> {t`Email`}</>
                                    }
                                </span>
                            </div>
                            <div className={classes.meta}>
                                {relativeDate(transfer.transferred_at)}
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
};
