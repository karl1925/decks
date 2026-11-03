<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Users · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('projects.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="{{ asset('theme.css') }}">
</head>
<body class="project-workspace">
    @include('partials.admin-sidebar', ['activeNav' => 'users'])

    <main class="project-main">
        <header class="project-header bg-gradient-to-r from-sky-50 via-white to-indigo-50 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950">
            <div><div class="project-eyebrow">ADMINISTRATION / USERS</div><h1>Users</h1><p>Create and manage admin access to Decks.</p></div>
            <div class="project-header-actions"><a class="project-button secondary" href="{{ route('lectures.index') }}">← Lectures</a><button class="project-button primary" type="button" onclick="openAdminModal()">＋ Add admin</button></div>
        </header>
        @if (session('message'))<div class="project-toast" role="status">{{ session('message') }}</div>@endif
        @if ($errors->any())<div class="project-error" role="alert">{{ $errors->first() }}</div>@endif

        <section class="project-table-card" aria-label="Admin accounts list">
            <div class="project-table-top"><div><h2>Admin accounts</h2><p>{{ $admins->total() }} {{ Str::plural('account', $admins->total()) }} · The seeded superadmin account is reserved.</p></div></div>
            <div class="project-table-scroll"><table class="project-table">
                <thead><tr><th>NAME</th><th>EMAIL</th><th>ACCOUNT TYPE</th><th class="actions-heading">ACTIONS</th></tr></thead>
                <tbody>
                    @forelse($admins as $admin)
                        <tr>
                            <td><div class="project-name">{{ $admin->name }}</div><div class="project-description">Added {{ $admin->created_at?->format('M j, Y') }}</div></td>
                            <td><span class="project-author">{{ $admin->email }}</span></td>
                            <td><span class="project-role-badge">Admin</span></td>
                            <td><div class="project-row-actions">
                                <button class="project-icon-action" type="button" aria-label="Edit {{ $admin->name }}" title="Edit account" data-admin-edit data-id="{{ $admin->id }}" data-name="{{ $admin->name }}" data-email="{{ $admin->email }}">✎</button>
                                <form method="post" action="{{ route('users.destroy', $admin) }}" data-confirm="Delete admin account {{ $admin->email }}? This cannot be undone.">@csrf @method('DELETE')<button class="project-icon-action delete-action" type="submit" title="Delete account" aria-label="Delete {{ $admin->name }}">×</button></form>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="project-empty"><span>♙</span><strong>No admin accounts yet.</strong><small>Add an admin account to invite another administrator.</small></td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @if($admins->hasPages())<div class="project-pagination">@include('partials.project-pagination', ['paginator' => $admins])</div>@endif
        </section>
    </main>

    <dialog class="project-modal" id="admin-modal" aria-labelledby="admin-modal-title">
        <form id="admin-form" method="post" action="{{ route('users.store') }}">
            @csrf
            <input type="hidden" id="admin-method" name="_method" value="">
            <div class="project-modal-header"><div><div class="project-eyebrow">LECTURER ACCESS</div><h2 id="admin-modal-title">Add admin account</h2><p>Users with admin access can manage lectures and use the Studio.</p></div><button class="project-modal-close" type="button" onclick="document.getElementById('admin-modal').close()" aria-label="Close">×</button></div>
            <label>Full name<input id="admin-name" name="name" maxlength="255" required></label>
            <label>Email address<input id="admin-email" name="email" type="email" maxlength="255" required></label>
            <label>Password <span id="admin-password-hint">(12 characters minimum)</span><input id="admin-password" name="password" type="password" minlength="12" autocomplete="new-password"></label>
            <label>Confirm password<input id="admin-password-confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password"></label>
            <div class="project-modal-actions"><button class="project-button secondary" type="button" onclick="document.getElementById('admin-modal').close()">Cancel</button><button class="project-button primary" id="admin-save" type="submit">Create account</button></div>
        </form>
    </dialog>

    <script>
        const adminModal = document.getElementById('admin-modal');
        const adminForm = document.getElementById('admin-form');
        const adminMethod = document.getElementById('admin-method');
        const adminEditTemplate = @js(route('users.update', ['user' => '__USER__']));

        function openAdminModal(admin = null) {
            adminForm.action = admin ? adminEditTemplate.replace('__USER__', admin.id) : @js(route('users.store'));
            adminMethod.value = admin ? 'PUT' : '';
            document.getElementById('admin-modal-title').textContent = admin ? 'Edit admin account' : 'Add admin account';
            document.getElementById('admin-save').textContent = admin ? 'Save changes' : 'Create account';
            document.getElementById('admin-name').value = admin?.name ?? '';
            document.getElementById('admin-email').value = admin?.email ?? '';
            document.getElementById('admin-password').required = !admin;
            document.getElementById('admin-password-confirmation').required = !admin;
            document.getElementById('admin-password').value = '';
            document.getElementById('admin-password-confirmation').value = '';
            document.getElementById('admin-password-hint').textContent = admin ? '(leave blank to keep the current password)' : '(12 characters minimum)';
            adminModal.showModal();
        }

        document.querySelectorAll('[data-admin-edit]').forEach((button) => button.addEventListener('click', () => openAdminModal(button.dataset)));
    </script>
</body>
</html>
