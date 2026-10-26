<x-mail::message>
# Lệnh rút tiền {{ $withdrawal->status === 'paid' ? 'đã được chuyển' : 'bị từ chối' }}

Xin chào {{ $withdrawal->user->name }},

@if ($withdrawal->status === 'paid')
SportHub đã chuyển **{{ number_format($withdrawal->amount, 0, ',', '.') }}đ** tới tài khoản **{{ $withdrawal->account_holder }}** — {{ $withdrawal->bank_name }} {{ $withdrawal->account_number }}@if ($withdrawal->reference) (mã giao dịch {{ $withdrawal->reference }})@endif.
@else
Lệnh rút **{{ number_format($withdrawal->amount, 0, ',', '.') }}đ** của bạn không được thực hiện{{ $withdrawal->reason ? ': '.$withdrawal->reason : '' }}. Số tiền đã được trả lại ví SportHub của bạn.
@endif

Trân trọng,<br>
{{ config('app.name') }}
</x-mail::message>
