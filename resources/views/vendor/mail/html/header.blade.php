@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" class="brand-lockup" target="_blank" rel="noopener">
{!! $slot !!}
</a>
</td>
</tr>
