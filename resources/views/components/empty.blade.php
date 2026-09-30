@props(['icon' => 'bi-inbox', 'message' => 'No records found for the selected filters.', 'colspan' => null])
@if($colspan)
    <tr><td colspan="{{ $colspan }}"><div class="empty-state"><i class="bi {{ $icon }}"></i>{{ $message }}{{ $slot }}</div></td></tr>
@else
    <div class="empty-state"><i class="bi {{ $icon }}"></i>{{ $message }}{{ $slot }}</div>
@endif
