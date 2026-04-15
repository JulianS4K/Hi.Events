import {t} from "@lingui/macro";
import {
    Button,
    Group,
    Modal,
    Stack,
    Text,
    TextInput,
} from "@mantine/core";
import {useForm} from "@mantine/form";
import {Attendee} from "../../../../../types.ts";
import classes from "./TransferTicketModal.module.scss";

interface TransferTicketModalProps {
    opened: boolean;
    onClose: () => void;
    attendee: Attendee;
    onSuccess: (data: { to_identifier: string; to_identifier_type: 'email' | 'phone' }) => void;
    isLoading?: boolean;
    transferred?: boolean;
    transferredTo?: string;
}

export const TransferTicketModal = ({
    opened,
    onClose,
    attendee,
    onSuccess,
    isLoading = false,
    transferred = false,
    transferredTo = '',
}: TransferTicketModalProps) => {

    const form = useForm({
        initialValues: {
            to_identifier_type: 'email' as 'email' | 'phone',
            to_identifier: '',
        },
        validate: {
            to_identifier: (value) => {
                if (!value.trim()) {
                    return t`Email address is required`;
                }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    return t`Please enter a valid email address`;
                }
                if (value.toLowerCase() === attendee.email?.toLowerCase()) {
                    return t`You cannot transfer a ticket to yourself`;
                }
                return null;
            },
        },
    });

    const handleClose = () => {
        form.reset();
        onClose();
    };

    const handleSubmit = (values: typeof form.values) => {
        onSuccess({
            to_identifier: values.to_identifier.trim(),
            to_identifier_type: values.to_identifier_type,
        });
    };

    return (
        <Modal
            opened={opened}
            onClose={handleClose}
            title={t`Transfer Ticket`}
            size="md"
            className={classes.modal}
        >
            {transferred ? (
                <div className={classes.successBox}>
                    <div className={classes.successIcon}>🎉</div>
                    <div className={classes.successTitle}>{t`Ticket transferred`}</div>
                    <div className={classes.successSubtitle}>
                        {t`This ticket has been transferred to`}{' '}
                        <strong>{transferredTo}</strong>.{' '}
                        {t`A confirmation has been sent to your email.`}
                    </div>
                    <Button mt="md" onClick={handleClose}>{t`Done`}</Button>
                </div>
            ) : (
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <Stack gap="md">
                        <Text size="sm" c="dimmed">
                            {t`Transfer this ticket to someone else. This action is immediate and cannot be undone.`}
                        </Text>

                        <TextInput
                            label={t`Recipient email address`}
                            placeholder={t`name@example.com`}
                            description={t`The recipient will receive their ticket at this address`}
                            type="email"
                            required
                            {...form.getInputProps('to_identifier')}
                        />

                        <div className={classes.warningBox}>
                            ⚠️ {t`Transfers are final. Once sent, this ticket will be removed from your account and cannot be reclaimed.`}
                        </div>

                        <Group justify="flex-end" mt="xs">
                            <Button variant="subtle" onClick={handleClose} disabled={isLoading}>
                                {t`Cancel`}
                            </Button>
                            <Button
                                type="submit"
                                loading={isLoading}
                                color="blue"
                            >
                                {t`Transfer ticket`}
                            </Button>
                        </Group>
                    </Stack>
                </form>
            )}
        </Modal>
    );
};

export type {TransferTicketModalProps};
