<?php /** Sign-in form. Errors are deliberately generic. */ ?>
<div class="brand" style="padding-left:0;margin-bottom:var(--space-5)">
    <img src="<?= e(asset('images/logo.svg')) ?>" alt="">
    <span><strong>Clinic CRM</strong></span>
</div>
<h1>Welcome back</h1>
<p class="lead">Sign in with your staff account to continue.</p>

<form method="post" action="<?= e(url('/login')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field" style="margin-bottom:var(--space-4)">
        <label for="email">Email address</label>
        <div class="input-icon">
            <?= icon('envelope') ?>
            <input class="input" id="email" name="email" type="email" autocomplete="username" required autofocus value="<?= e(old('email')) ?>" placeholder="you@clinic.com">
        </div>
    </div>
    <div class="field" style="margin-bottom:var(--space-5)">
        <label for="password">Password</label>
        <div class="input-icon">
            <?= icon('lock') ?>
            <input class="input" id="password" name="password" type="password" autocomplete="current-password" required placeholder="Your password">
        </div>
        <button type="button" class="btn btn-ghost btn-sm" style="margin-top:6px" data-toggle-password="#password" aria-pressed="false"><?= icon('eye') ?> Show password</button>
    </div>
    <button class="btn btn-primary btn-block" type="submit" style="padding:12px"><?= icon('box-arrow-in-right') ?> Sign in</button>
</form>
<p class="help" style="margin-top:var(--space-5)">Trouble signing in? Ask your clinic administrator to check your account. Password reset by email is not available yet - an administrator can issue a temporary password.</p>
