<section class="client-slide group-roster-slide" data-presented-group="{{ $presented_group['label'] }}">
    <div class="slide-topline"><span>GROUP ROSTER</span><span>{{ count($presented_group['members']) }} MEMBERS</span></div>
    <div class="group-roster-content">
        <div class="eyebrow">EVENT GROUP</div>
        <h1>Group {{ $presented_group['label'] }}</h1>
        <table class="group-roster-table"><thead><tr><th>#</th><th>MEMBER</th><th>ROLE</th></tr></thead><tbody>
            @forelse($presented_group['members'] as $index => $member)
                <tr><td>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td><td>{{ $member->name }}</td><td>{{ $member->is_group_leader ? 'Group leader' : 'Member' }}</td></tr>
            @empty
                <tr><td colspan="3">No members are registered in this group yet.</td></tr>
            @endforelse
        </tbody></table>
    </div>
    <div class="client-slide-footer"><span>GROUP {{ $presented_group['label'] }}</span><span>WORK TOGETHER</span></div>
</section>
