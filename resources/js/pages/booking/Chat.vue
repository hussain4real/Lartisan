<script setup lang="ts">
import { Form, Head, Link, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, LockKeyhole, MessageSquare, Send } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { store as storeArtisanMessage } from '@/routes/artisan/bookings/chat/messages';
import { store as storeCustomerMessage } from '@/routes/customer/bookings/chat/messages';
import type { BookingChatMessage, BookingChatPage } from '@/types';

const props = defineProps<BookingChatPage>();

const messageForm = computed(() => {
    if (props.role === 'artisan' && props.currentTeamSlug) {
        return storeArtisanMessage.form({
            current_team: props.currentTeamSlug,
            booking: props.booking.id,
        });
    }

    return storeCustomerMessage.form(props.booking.id);
});

usePoll(
    5000,
    {
        only: ['messages', 'canSend'],
    },
    {
        mode: 'rest',
    },
);

const formatDate = (message: BookingChatMessage) => {
    if (!message.createdAt) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(message.createdAt));
};
</script>

<template>
    <Head :title="`Booking chat ${booking.trackerCode}`" />

    <main class="min-h-screen bg-background text-foreground">
        <div
            class="mx-auto grid min-h-screen w-full max-w-4xl grid-rows-[auto_1fr_auto] gap-5 px-4 py-5 sm:px-6"
        >
            <header class="grid gap-4">
                <nav class="flex items-center justify-between gap-3">
                    <Button as-child variant="ghost" size="sm">
                        <Link :href="backUrl">
                            <ArrowLeft />
                            Back
                        </Link>
                    </Button>
                    <Badge variant="secondary">{{ booking.status }}</Badge>
                </nav>

                <section class="grid gap-3 rounded-lg border p-4">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="space-y-1">
                            <p class="text-sm text-muted-foreground">
                                {{ booking.trackerCode }}
                            </p>
                            <h1 class="text-xl font-semibold">
                                <template v-if="role === 'customer'">
                                    {{ booking.artisan.businessName }}
                                </template>
                                <template v-else>
                                    {{ booking.customerName }}
                                </template>
                            </h1>
                            <p v-if="booking.service" class="text-sm">
                                {{ booking.service.title }} -
                                {{ booking.service.category }}
                            </p>
                        </div>
                        <Badge variant="outline" class="w-fit gap-1.5">
                            <MessageSquare class="size-3.5" />
                            {{ role }}
                        </Badge>
                    </div>

                    <div
                        class="flex items-start gap-2 rounded-md bg-muted/40 p-3 text-sm text-muted-foreground"
                    >
                        <LockKeyhole class="mt-0.5 size-4 shrink-0" />
                        <p>{{ privacyNotice }}</p>
                    </div>
                </section>
            </header>

            <section
                class="min-h-[320px] overflow-hidden rounded-lg border bg-muted/20"
            >
                <div class="flex h-full flex-col gap-3 overflow-y-auto p-4">
                    <TransitionGroup
                        v-if="messages.length > 0"
                        name="message"
                        tag="div"
                        class="grid gap-3"
                    >
                        <article
                            v-for="message in messages"
                            :key="message.id"
                            class="flex"
                            :class="
                                message.mine ? 'justify-end' : 'justify-start'
                            "
                        >
                            <div
                                class="max-w-[85%] rounded-lg border px-4 py-3 shadow-sm sm:max-w-[70%]"
                                :class="
                                    message.mine
                                        ? 'border-primary/30 bg-primary text-primary-foreground'
                                        : 'bg-background'
                                "
                            >
                                <div
                                    class="mb-1 flex flex-wrap items-center gap-2 text-xs"
                                    :class="
                                        message.mine
                                            ? 'text-primary-foreground/80'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    <span>{{ message.senderName }}</span>
                                    <span v-if="message.createdAt">
                                        {{ formatDate(message) }}
                                    </span>
                                </div>
                                <p
                                    class="text-sm leading-6 whitespace-pre-wrap"
                                >
                                    {{ message.body }}
                                </p>
                            </div>
                        </article>
                    </TransitionGroup>

                    <div
                        v-else
                        class="grid flex-1 place-items-center rounded-md border border-dashed bg-background/70 p-8 text-center text-sm text-muted-foreground"
                    >
                        No messages yet.
                    </div>
                </div>
            </section>

            <Form
                v-if="canSend"
                v-bind="messageForm"
                :reset-on-success="['body']"
                class="grid gap-3 rounded-lg border bg-background p-4"
                #default="{ errors, processing, recentlySuccessful }"
            >
                <label class="grid gap-2 text-sm font-medium">
                    Message
                    <textarea
                        name="body"
                        rows="3"
                        maxlength="1200"
                        class="resize-none rounded-md border bg-background p-3 text-sm transition outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                        placeholder="Write a booking-safe update"
                        required
                    />
                    <InputError :message="errors.body" />
                </label>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-xs text-muted-foreground">
                        Contact details and external links are blocked.
                    </p>
                    <div class="flex items-center gap-3">
                        <span
                            v-if="recentlySuccessful"
                            class="text-xs text-muted-foreground"
                        >
                            Sent
                        </span>
                        <Button type="submit" :disabled="processing">
                            <Send />
                            Send
                        </Button>
                    </div>
                </div>
            </Form>

            <div
                v-else
                class="rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground"
            >
                Chat is closed for this booking state.
            </div>
        </div>
    </main>
</template>

<style scoped>
.message-enter-active {
    transition:
        opacity 160ms ease,
        transform 160ms ease;
}

.message-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

@media (prefers-reduced-motion: reduce) {
    .message-enter-active {
        transition: none;
    }

    .message-enter-from {
        opacity: 1;
        transform: none;
    }
}
</style>
