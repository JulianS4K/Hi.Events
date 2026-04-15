<?php

namespace HiEvents\Http\Request\TicketTransfer;

use HiEvents\Http\Request\BaseRequest;

class TransferTicketPublicRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'to_identifier'      => ['required', 'string', 'max:255'],
            'to_identifier_type' => ['required', 'string', 'in:email,phone'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type  = $this->input('to_identifier_type');
            $value = $this->input('to_identifier');

            if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $validator->errors()->add('to_identifier', __('Please enter a valid email address.'));
            }

            if ($type === 'phone' && !preg_match('/^\+?[0-9\s\-().]{7,30}$/', $value)) {
                $validator->errors()->add('to_identifier', __('Please enter a valid phone number.'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'to_identifier.required'      => __('Please enter an email address or phone number.'),
            'to_identifier_type.required' => __('Please specify the transfer type.'),
            'to_identifier_type.in'       => __('Transfer type must be email or phone.'),
        ];
    }
}
