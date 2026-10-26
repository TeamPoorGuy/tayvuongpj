<x-mail::message>
# Ví SportHub đã nhận tiền

Xin chào {{ $user->name }},

Ví SportHub của bạn vừa được cộng **{{ number_format($amount, 0, ',', '.') }}đ**.

**Lý do:** {{ $reason }}

Số dư hiện tại: **{{ number_format($user->wallet_balance, 0, ',', '.') }}đ**. Bạn có thể dùng số dư này để đặt sân hoặc rút về tài khoản ngân hàng bất cứ lúc nào.

Trân trọng,<br>
{{ config('app.name') }}
</x-mail::message>
