<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { FileUp, ShieldCheck } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as artisanDashboard } from '@/routes/artisan';
import { store } from '@/routes/artisan/kyc';
import type {
    ArtisanDashboardProfile,
    KycSubmissionDetail,
    Team,
} from '@/types';

type Props = {
    currentTeam: Team;
    profile: Pick<
        ArtisanDashboardProfile,
        'id' | 'businessName' | 'verificationStatus'
    >;
    collections: string[];
    latestSubmission: KycSubmissionDetail | null;
};

const props = defineProps<Props>();

const collectionLabel = (collection: string) =>
    collection
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Artisan',
                href: artisanDashboard(props.currentTeam.slug).url,
            },
            {
                title: 'KYC',
                href: '#',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Artisan KYC" />

    <h1 class="sr-only">Artisan KYC</h1>

    <div class="flex flex-col gap-8 p-4 sm:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="KYC"
                :description="profile.businessName"
            />
            <Badge variant="secondary" class="w-fit gap-1.5">
                <ShieldCheck class="size-3.5" />
                {{ profile.verificationStatus }}
            </Badge>
        </div>

        <section
            class="grid gap-5 rounded-lg border p-5 lg:grid-cols-[1fr_1.4fr]"
        >
            <Heading variant="small" title="Submit evidence" />

            <Form
                v-bind="store.form(props.currentTeam.slug)"
                class="grid gap-5"
                v-slot="{ errors, processing, progress }"
            >
                <div
                    v-for="collection in collections"
                    :key="collection"
                    class="grid gap-2"
                >
                    <Label :for="collection">{{
                        collectionLabel(collection)
                    }}</Label>
                    <Input
                        :id="collection"
                        :name="collection"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                    />
                    <InputError :message="errors[collection]" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notes</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        maxlength="2000"
                        class="min-h-24 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <progress
                    v-if="progress"
                    class="h-2 w-full"
                    :value="progress.percentage"
                    max="100"
                />

                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing">
                        <FileUp />
                        Submit
                    </Button>
                </div>
            </Form>
        </section>

        <section class="space-y-4">
            <Heading variant="small" title="Latest submission" />

            <div v-if="latestSubmission" class="rounded-lg border p-5">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="font-medium">
                            Submission #{{ latestSubmission.id }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                latestSubmission.submittedAt || 'Not submitted'
                            }}
                        </p>
                    </div>
                    <Badge variant="outline">{{
                        latestSubmission.status
                    }}</Badge>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="collection in collections"
                        :key="collection"
                        class="rounded-md border p-3 text-sm"
                    >
                        <p class="font-medium">
                            {{ collectionLabel(collection) }}
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            {{
                                latestSubmission.media[collection]?.fileName ??
                                'No file'
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No KYC submission yet.
            </p>
        </section>
    </div>
</template>
