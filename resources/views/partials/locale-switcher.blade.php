@php $locale = app()->getLocale(); @endphp
<div class="flex items-center gap-1 rounded-full bg-white/5 p-0.5 text-[11px] font-medium ring-1 ring-white/10">
    <a href="{{ route('locale.set', 'zh_TW') }}"
       class="rounded-full px-2 py-0.5 transition {{ $locale === 'zh_TW' ? 'bg-white/15 text-slate-200' : 'text-slate-500 hover:text-slate-300' }}">
        中
    </a>
    <a href="{{ route('locale.set', 'en') }}"
       class="rounded-full px-2 py-0.5 transition {{ $locale === 'en' ? 'bg-white/15 text-slate-200' : 'text-slate-500 hover:text-slate-300' }}">
        EN
    </a>
</div>
