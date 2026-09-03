<?php
/** @var array $users */
/** @var array $roles */
/** @var array $branches */
/** @var bool $isOwner */
?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">Users</h2>
        <p class="text-muted" style="margin:0;">Manage store user accounts and roles</p>
    </div>
    <div class="flex gap-8">
        <a href="<?= url('/roles') ?>" class="btn btn-outline"><?= icon('settings', 16) ?> Roles &amp; Permissions</a>
        <button type="button" class="btn btn-primary" data-modal-target="#add-user"><?= icon('plus', 16) ?> Add User</button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Username</th><th>Role</th><?php if ($isOwner): ?><th>Branch</th><?php endif; ?><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e($u['name']) ?></strong></td>
                    <td class="text-muted"><?= e($u['username']) ?></td>
                    <td><span class="badge badge-blue"><?= e($u['role_name']) ?></span></td>
                    <?php if ($isOwner): ?><td class="text-muted"><?= e($u['branch_name'] ?? '—') ?></td><?php endif; ?>
                    <td><span class="badge <?= $u['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                    <td class="text-muted"><?= $u['last_login_at'] ? date('M d, Y h:i A', strtotime($u['last_login_at'])) : 'Never' ?></td>
                    <td>
                        <div class="flex gap-8">
                            <button type="button" class="btn btn-sm btn-outline" data-modal-target="#edit-user-<?= $u['id'] ?>"><?= icon('edit', 14) ?></button>
                            <?php if ((int) $u['id'] !== (int) ($currentUser['id'] ?? 0)): ?>
                            <form method="post" action="<?= url('/users/' . $u['id'] . '/deactivate') ?>" data-confirm="<?= $u['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?> this user?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline"><?= icon($u['status'] === 'active' ? 'x' : 'check', 14) ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="add-user">
    <div class="modal">
        <h3>Add User</h3>
        <form method="post" action="<?= url('/users') ?>">
            <?= csrf_field() ?>
            <div class="form-group"><label>Full Name</label><input class="form-control" name="name" required></div>
            <div class="form-row">
                <div class="form-group"><label>Username</label><input class="form-control" name="username" required></div>
                <div class="form-group"><label>Role</label>
                    <select class="form-control" name="role_id">
                        <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($isOwner && count($branches) > 1): ?>
            <div class="form-group"><label>Branch</label>
                <select class="form-control" name="branch_id">
                    <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group"><label>Email (optional)</label><input class="form-control" type="email" name="email"></div>
            <div class="form-group"><label>Password</label><input class="form-control" type="password" name="password" minlength="8" required></div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
        </form>
    </div>
</div>

<?php foreach ($users as $u): ?>
<div class="modal-backdrop" id="edit-user-<?= $u['id'] ?>">
    <div class="modal">
        <h3>Edit User — <?= e($u['name']) ?></h3>
        <form method="post" action="<?= url('/users/' . $u['id']) ?>">
            <?= csrf_field() ?>
            <div class="form-group"><label>Full Name</label><input class="form-control" name="name" value="<?= e($u['name']) ?>" required></div>
            <div class="form-row">
                <div class="form-group"><label>Username</label><input class="form-control" value="<?= e($u['username']) ?>" disabled></div>
                <div class="form-group"><label>Role</label>
                    <select class="form-control" name="role_id">
                        <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>" <?= (int) $r['id'] === (int) $u['role_id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" value="<?= e($u['email'] ?? '') ?>"></div>
            <div class="form-group"><label>New Password (leave blank to keep current)</label><input class="form-control" type="password" name="password" minlength="8"></div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
        </form>
    </div>
</div>
<?php endforeach; ?>
