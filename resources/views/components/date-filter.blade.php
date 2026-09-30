@props(['range', 'autosubmit' => false])
<div class="col-6 col-md-auto">
    <label class="form-label">Period</label>
    <select name="period" class="form-select form-select-sm" @if($autosubmit) data-autosubmit-period @endif>
        @foreach(\App\Support\DateRange::PERIODS as $k => $label)
            <option value="{{ $k }}" @selected($range->period === $k)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="col-6 col-md-auto" data-custom-range>
    <label class="form-label">From</label>
    <input type="date" name="from" value="{{ $range->fromDate() }}" class="form-control form-control-sm">
</div>
<div class="col-6 col-md-auto" data-custom-range>
    <label class="form-label">To</label>
    <input type="date" name="to" value="{{ $range->toDate() }}" class="form-control form-control-sm">
</div>
