import { useForm } from '@inertiajs/react';
import { Rocket } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** Puts a draft event live. */
export function PublishButton({ eventId }: { eventId: number }) {
    const form = useForm({});

    return (
        <Button
            type="button"
            size="sm"
            className="bg-mint-ink hover:bg-mint-ink/90"
            disabled={form.processing}
            onClick={() => form.post(route('organizer.events.publish', eventId), { preserveScroll: true })}
        >
            <Rocket className="size-3.5" />Publish
        </Button>
    );
}
