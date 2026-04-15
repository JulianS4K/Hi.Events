import {getAttendeeProductPrice, getAttendeeProductTitle} from "../../../utilites/products.ts";
import {Button, CopyButton} from "@mantine/core";
import {formatCurrency} from "../../../utilites/currency.ts";
import {t} from "@lingui/macro";
import {prettyDate} from "../../../utilites/dates.ts";
import QRCode from "react-qr-code";
import {IconCopy, IconPrinter, IconLock, IconX} from "@tabler/icons-react";
import {Address, Attendee, Event, Product} from "../../../types.ts";
import classes from './AttendeeTicket.module.scss';
import {imageUrl} from "../../../utilites/urlHelper.ts";
import {formatAddress} from "../../../utilites/addressUtilities.ts";
import {PoweredByFooter} from "../PoweredByFooter";
import {useQrToken} from "../../../hooks/useQrToken.ts";

interface AttendeeTicketProps {
    event: Event;
    attendee: Attendee;
    product: Product;
    hideButtons?: boolean;
    showPoweredBy?: boolean;
}

export const AttendeeTicket = ({
                                   attendee,
                                   product,
                                   event,
                                   hideButtons = false,
                                   showPoweredBy = false,
                               }: AttendeeTicketProps) => {
    const productPrice = getAttendeeProductPrice(attendee, product);
    const hasVenue = event?.settings?.location_details?.venue_name || event?.settings?.location_details?.address_line_1;

    const ticketDesignSettings = event?.settings?.ticket_design_settings;
    const accentColor = ticketDesignSettings?.accent_color || '#6B46C1';
    const footerText = ticketDesignSettings?.footer_text;
    const logoUrl = imageUrl('TICKET_LOGO', event?.images);

    const ticketStyle = {
        '--accent': accentColor,
    } as React.CSSProperties;

    const isCancelled = attendee.status === 'CANCELLED';
    const isAwaitingPayment = attendee.status === 'AWAITING_PAYMENT';
    const rotationEnabled = !!event?.settings?.qr_rotation_enabled;

    const qrToken = useQrToken(
        event?.id,
        attendee.short_id,
        rotationEnabled && !isCancelled && !isAwaitingPayment,
    );

    const qrValue = rotationEnabled && qrToken.token
        ? qrToken.token
        : String(attendee.public_id);

    // Generate a deterministic pattern based on attendee ID for consistency
    const generateQrPattern = () => {
        const seed = attendee.public_id || 'default';
        const pattern = [];
        for (let i = 0; i < 64; i++) {
            const charCode = seed.charCodeAt(i % seed.length);
            pattern.push((charCode + i) % 2 === 0);
        }
        return pattern;
    };

    const qrPattern = generateQrPattern();

    return (
        <div className={classes.ticket} style={ticketStyle}>
            {/* Header */}
            <div className={classes.header}>
                <div className={classes.headerContent}>
                    <h1 className={classes.eventTitle}>{event?.title}</h1>
                    <div className={classes.priceDisplay}>
                        {productPrice > 0 ? formatCurrency(productPrice, event?.currency) : t`Free`}
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <div className={classes.content}>
                <div className={classes.contentLeft}>
                    {/* Event Details */}
                    <div className={classes.eventDetails}>
                        <div className={classes.detailRow}>
                            <div className={classes.detailLabel}>{t`Date & Time`}</div>
                            <div className={classes.detailValue}>
                                {prettyDate(event.start_date, event.timezone, true)}
                            </div>
                        </div>
                        {event?.organizer?.name && (
                            <div className={classes.detailRow}>
                                <div className={classes.detailLabel}>{t`Organizer`}</div>
                                <div className={classes.detailValue}>
                                    {event?.organizer?.name}
                                </div>
                            </div>
                        )}

                        {hasVenue && (
                            <div className={classes.detailRow}>
                                <div className={classes.detailLabel}>{t`Location`}</div>
                                <div className={classes.detailValue}>
                                    {formatAddress(event?.settings?.location_details as Address)}
                                </div>
                            </div>
                        )}

                        <div className={classes.detailRow}>
                            <div className={classes.detailLabel}>{t`Ticket Type`}</div>
                            <div className={classes.detailValue}>
                                {getAttendeeProductTitle(attendee, product)}
                            </div>
                        </div>
                    </div>

                    {/* Attendee Information */}
                    <div className={classes.attendeeSection}>
                        <div className={classes.detailLabel}>{t`Attendee`}</div>
                        <div className={classes.attendeeName}>
                            {attendee.first_name} {attendee.last_name}
                        </div>
                        <div className={classes.attendeeEmail}>{attendee.email}</div>
                    </div>

                </div>

                {/* Right Section - Logo & QR Code */}
                <div className={classes.contentRight}>
                    <div className={classes.qrSection}>
                        {logoUrl && (
                            <div className={classes.logoContainer}>
                                <img src={logoUrl} alt="Event Logo" className={classes.logo}/>
                            </div>
                        )}

                        {/* QR Code or Status Placeholder */}
                        {(isCancelled || isAwaitingPayment) ? (
                            <div className={`${classes.qrPlaceholder} ${isCancelled ? classes.qrPlaceholderCancelled : classes.qrPlaceholderPending}`}>
                                {/* Faded QR Pattern Background */}
                                <div className={classes.qrPatternBackground}>
                                    {qrPattern.map((filled, i) => (
                                        <div
                                            key={i}
                                            className={`${classes.qrPatternCell} ${filled ? classes.qrPatternCellFilled : ''}`}
                                        />
                                    ))}
                                </div>

                                {/* Status Content Overlay */}
                                <div className={classes.qrPlaceholderContent}>
                                    <div className={`${classes.statusIconCircle} ${isCancelled ? classes.statusIconCancelled : classes.statusIconPending}`}>
                                        {isCancelled ? (
                                            <IconX size={20} stroke={2} color="white" />
                                        ) : (
                                            <IconLock size={20} stroke={2} color="white" />
                                        )}
                                    </div>
                                    <span className={`${classes.statusText} ${isCancelled ? classes.statusTextCancelled : classes.statusTextPending}`}>
                                        {isCancelled ? t`Cancelled` : t`Pay to unlock`}
                                    </span>
                                </div>
                            </div>
                        ) : (
                            <div
                                className={classes.qrContainer}
                                style={{borderColor: accentColor}}
                            >
                                {rotationEnabled && (
                                    <div className={classes.qrCountdown}>
                                        <svg viewBox="0 0 36 36" className={classes.qrCountdownRing}>
                                            <circle
                                                cx="18" cy="18" r="16"
                                                fill="none"
                                                stroke={accentColor}
                                                strokeOpacity="0.15"
                                                strokeWidth="2"
                                            />
                                            <circle
                                                cx="18" cy="18" r="16"
                                                fill="none"
                                                stroke={accentColor}
                                                strokeWidth="2"
                                                strokeDasharray={`${(qrToken.secondsLeft / 30) * 100.5} 100.5`}
                                                strokeLinecap="round"
                                                transform="rotate(-90 18 18)"
                                                style={{transition: 'stroke-dasharray 1s linear'}}
                                            />
                                        </svg>
                                        <span className={classes.qrCountdownLabel}>
                                            {qrToken.secondsLeft}s
                                        </span>
                                    </div>
                                )}
                                <QRCode
                                    value={qrValue}
                                    size={180}
                                    level="M"
                                    style={{height: "auto", maxWidth: "100%", width: "100%"}}
                                />
                                {rotationEnabled && qrToken.isOffline && (
                                    <div className={classes.qrOfflineWarning}>
                                        {t`Offline — QR may be expired`}
                                    </div>
                                )}
                            </div>
                        )}

                        <div className={classes.ticketId}>
                            <div className={classes.detailLabel}>{t`Ticket ID`}</div>
                            <div
                                className={classes.ticketIdValue}
                                style={{color: accentColor}}
                            >{attendee.public_id}</div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Footer - Only show if there's footer text or buttons */}
            {(footerText || !hideButtons) && (
                <div className={classes.footer}>
                    <div className={classes.footerContent}>
                        {footerText && (
                            <div className={classes.footerText}>
                                {footerText}
                            </div>
                        )}

                        {!hideButtons && (
                            <div className={classes.actions}>
                                <Button
                                    variant="default"
                                    size="sm"
                                    onClick={() => window?.open(`/product/${event.id}/${attendee.short_id}/print`, '_blank')}
                                    leftSection={<IconPrinter size={16}/>}
                                >
                                    {t`Print to PDF`}
                                </Button>

                                <CopyButton
                                    value={`${window?.location.origin}/product/${event.id}/${attendee.short_id}`}>
                                    {({copied, copy}) => (
                                        <Button
                                            variant="default"
                                            size="sm"
                                            onClick={copy}
                                            leftSection={<IconCopy size={16}/>}
                                        >
                                            {copied ? t`Copied` : t`Copy Link`}
                                        </Button>
                                    )}
                                </CopyButton>

                                {event?.settings?.wallet_apple_enabled && (
                                    <a
                                        href={`/api/public/events/${event.id}/attendees/${attendee.short_id}/wallet/apple`}
                                        className={classes.walletButton}
                                        aria-label={t`Add to Apple Wallet`}
                                    >
                                        <img
                                            src="https://apple.com/v/wallet/b/images/overview/add_to_apple_wallet_badge.svg"
                                            alt={t`Add to Apple Wallet`}
                                            height={40}
                                            style={{display: 'block'}}
                                        />
                                    </a>
                                )}

                                {event?.settings?.wallet_google_enabled && (
                                    <button
                                        className={classes.walletButton}
                                        onClick={async () => {
                                            try {
                                                const res = await fetch(`/api/public/events/${event.id}/attendees/${attendee.short_id}/wallet/google`);
                                                const {url} = await res.json();
                                                window?.open(url, '_blank');
                                            } catch {
                                                // silently fail — button shouldn't show if unconfigured
                                            }
                                        }}
                                        aria-label={t`Save to Google Wallet`}
                                    >
                                        <img
                                            src="https://pay.google.com/about/static/images/social/google-wallet-badge.svg"
                                            alt={t`Save to Google Wallet`}
                                            height={40}
                                            style={{display: 'block'}}
                                        />
                                    </button>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Powered By - Only shown in print mode */}
            {showPoweredBy && (
                <div className={classes.poweredByInTicket}>
                    <PoweredByFooter/>
                </div>
            )}
        </div>
    );
}
