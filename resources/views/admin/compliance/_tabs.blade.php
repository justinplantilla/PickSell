<nav class="oversight-tabs" aria-label="Seller compliance">
    <a href="{{ route('admin.compliance') }}" class="oversight-tab {{ request()->routeIs('admin.compliance') ? 'is-active' : '' }}" @if(request()->routeIs('admin.compliance')) aria-current="page" @endif>Sellers</a>
    <a href="{{ route('admin.compliance.cases') }}" class="oversight-tab {{ request()->routeIs('admin.compliance.cases') ? 'is-active' : '' }}" @if(request()->routeIs('admin.compliance.cases')) aria-current="page" @endif>Cases <span class="oversight-tab-count">{{ number_format($openCaseCount) }} open</span></a>
</nav>
