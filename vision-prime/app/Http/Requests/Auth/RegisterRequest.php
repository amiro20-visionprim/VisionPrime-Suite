<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domains\Identity\Services\OtpService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^0?9[0-9]{9}$/', 'unique:users,phone'],
            'otp_code' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
            'terms' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'شماره تماس را وارد کنید.',
            'phone.regex' => 'شماره تماس معتبر نیست (مثال: 09123456789).',
            'phone.unique' => 'این شماره تماس قبلاً ثبت شده است.',
            'otp_code.required' => 'کد تأیید را وارد کنید.',
            'otp_code.digits' => 'کد تأیید باید ۶ رقم باشد.',
            'terms.required' => 'برای ثبت‌نام باید قوانین و سیاست‌های سوئیت را بپذیرید.',
            'terms.accepted' => 'برای ثبت‌نام باید قوانین و سیاست‌های سوئیت را بپذیرید.',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
        ];
    }

    /** @return array<string, string> */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => OtpService::normalizePhone((string) $this->input('phone')),
            ]);
        }
    }
}
