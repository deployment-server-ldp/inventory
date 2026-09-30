<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>
    @page { margin: 22px 24px 30px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1a2744; }
    h1 { font-size: 14px; margin: 0 0 2px; color: #0f1f3d; }
    .company { font-size: 10px; color: #475569; margin-bottom: 6px; }
    .meta td { padding: 1px 8px 1px 0; font-size: 8.5px; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.data th { background: #1e3a8a; color: #fff; text-align: left; padding: 4px; font-size: 8px; }
    table.data td { border-bottom: 1px solid #e3e8f0; padding: 3px 4px; }
    table.data tr:nth-child(even) td { background: #f8fafc; }
    .num { text-align: right; }
    .footer { position: fixed; bottom: -18px; left: 0; right: 0; font-size: 7.5px; color: #64748b; }
</style></head>
<body>
<div class="company">{{ \App\Models\AppSetting::get('company_name') }} @if(\App\Models\AppSetting::get('company_address')) · {{ \App\Models\AppSetting::get('company_address') }} @endif</div>
<h1>{{ $title }}</h1>
<table class="meta">@foreach($meta as $k => $v)<tr><td><strong>{{ $k }}</strong></td><td>{{ $v }}</td></tr>@endforeach
    <tr><td><strong>Generated</strong></td><td>{{ now()->format('d M Y H:i') }} by {{ auth()->user()?->name ?? 'system' }}</td></tr></table>
@if($truncated)<p style="color:#b91c1c">Only the first {{ number_format(\App\Services\ExportService::PDF_ROW_LIMIT) }} rows are included in the PDF. Use Excel/CSV for the full data set.</p>@endif
<table class="data">
    <thead><tr>@foreach($headings as $i => $h)<th class="{{ ($numeric[$i] ?? false) ? 'num' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($rows as $row)
        <tr>@foreach(array_values($row) as $i => $v)<td class="{{ ($numeric[$i] ?? false) ? 'num' : '' }}">{{ is_float($v) ? rtrim(rtrim(number_format($v, 3), '0'), '.') : $v }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($headings) }}">No records for the selected filters.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="footer">SPIMS · {{ $title }}</div>
</body></html>
