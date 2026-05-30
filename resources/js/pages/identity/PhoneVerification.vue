<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CheckCircle2, Send, Smartphone } from 'lucide-vue-next';
import { ref } from 'vue';
import {
    issue,
    verify,
} from '@/actions/App/Http/Controllers/Identity/PhoneVerificationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Channel = {
    label: string;
    value: string;
};

type Phone = {
    countryCode: string;
    number: string | null;
    preferredChannel: string;
    verified: boolean;
    verifiedAt: string | null;
};

const props = defineProps<{
    channels: Channel[];
    phone: Phone;
}>();

const phoneCountryCode = ref(props.phone.countryCode);
const phoneNumber = ref(props.phone.number ?? '');
const preferredChannel = ref(props.phone.preferredChannel);
</script>

<template>
    <Head title="Phone verification" />

    <h1 class="sr-only">Phone verification</h1>

    <div class="flex flex-col gap-8">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Phone verification"
                description="Confirm the phone number used for OTP, account claiming, and booking updates."
            />

            <Badge variant="secondary" class="w-fit gap-1.5">
                <CheckCircle2 v-if="phone.verified" class="size-3.5" />
                <Smartphone v-else class="size-3.5" />
                {{ phone.verified ? 'Verified' : 'Pending' }}
            </Badge>
        </div>

        <section class="grid gap-5 rounded-lg border p-5 lg:grid-cols-2">
            <div class="space-y-3">
                <div
                    class="flex size-10 items-center justify-center rounded-md bg-muted text-muted-foreground"
                >
                    <Smartphone class="size-5" />
                </div>
                <Heading
                    variant="small"
                    title="Send OTP"
                    description="Choose where Lartisan should send the verification code."
                />
            </div>

            <Form
                v-bind="issue.form()"
                class="grid gap-5"
                v-slot="{ errors, processing }"
            >
                <input
                    type="hidden"
                    name="preferred_channel"
                    :value="preferredChannel"
                />

                <div class="grid gap-4 sm:grid-cols-[9rem_1fr]">
                    <div class="grid gap-2">
                        <Label for="phone_country_code">Code</Label>
                        <Input
                            id="phone_country_code"
                            v-model="phoneCountryCode"
                            name="phone_country_code"
                            autocomplete="tel-country-code"
                            placeholder="+234"
                            required
                        />
                        <InputError :message="errors.phone_country_code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone_number">Phone number</Label>
                        <Input
                            id="phone_number"
                            v-model="phoneNumber"
                            name="phone_number"
                            autocomplete="tel-national"
                            placeholder="8031234567"
                            required
                        />
                        <InputError :message="errors.phone_number" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="preferred_channel">Channel</Label>
                    <Select v-model="preferredChannel">
                        <SelectTrigger id="preferred_channel" class="w-full">
                            <SelectValue placeholder="Select channel" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="channel in channels"
                                :key="channel.value"
                                :value="channel.value"
                            >
                                {{ channel.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.preferred_channel" />
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing">
                        <Send />
                        Send code
                    </Button>
                </div>
            </Form>
        </section>

        <section class="grid gap-5 rounded-lg border p-5 lg:grid-cols-2">
            <div class="space-y-3">
                <Heading
                    variant="small"
                    title="Confirm OTP"
                    description="Enter the six digit code sent to the selected channel."
                />
            </div>

            <Form
                v-bind="verify.form()"
                class="grid gap-5"
                v-slot="{ errors, processing }"
                :reset-on-success="['code']"
            >
                <input
                    type="hidden"
                    name="phone_country_code"
                    :value="phoneCountryCode"
                />
                <input type="hidden" name="phone_number" :value="phoneNumber" />
                <input
                    type="hidden"
                    name="preferred_channel"
                    :value="preferredChannel"
                />

                <div class="grid gap-2">
                    <Label for="code">Verification code</Label>
                    <Input
                        id="code"
                        name="code"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        placeholder="123456"
                        required
                    />
                    <InputError :message="errors.code" />
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing">
                        <CheckCircle2 />
                        Verify
                    </Button>
                </div>
            </Form>
        </section>
    </div>
</template>
