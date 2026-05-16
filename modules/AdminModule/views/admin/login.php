<style>
.admin-auth-wrap {
    min-height: calc(100vh - 100px);
    display: grid;
    place-items: center;
    background: radial-gradient(circle at 20% 20%, #d9f3ff 0%, #f3f7fb 45%, #f7f1e8 100%);
    padding: 24px;
}
.auth-card {
    width: min(460px, 100%);
    background: #ffffff;
    border: 1px solid #d8e0e8;
    border-radius: 14px;
    box-shadow: 0 18px 50px rgba(16, 37, 55, 0.12);
    padding: 26px;
}
.auth-card h1 {
    margin: 0 0 6px;
    font-size: 1.6rem;
}
.auth-card p {
    margin: 0 0 18px;
    color: #5b6b7b;
}
.auth-grid {
    display: grid;
    gap: 12px;
}
.auth-grid .hidden-field {
    display: none;
}
.auth-grid label {
    font-weight: 600;
    color: #22313f;
}
.auth-grid input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #becad6;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 0.95rem;
}
.auth-btn {
    border: none;
    border-radius: 10px;
    background: linear-gradient(125deg, #0f5b8a, #1c7ea8);
    color: #fff;
    font-weight: 700;
    padding: 11px 14px;
    cursor: pointer;
}
.auth-grid .submit-field {
    margin-top: 2px;
}
.auth-alert {
    margin: 0 0 14px;
    background: #ffe8e8;
    border: 1px solid #f2b8b8;
    color: #9f2f2f;
    border-radius: 10px;
    padding: 10px 12px;
}
.auth-meta {
    margin-top: 14px;
    color: #607182;
    font-size: 0.9rem;
}
</style>

<section class="admin-auth-wrap">
    <div class="auth-card">
        <h1>Administrace</h1>
        <p>Přihlaste se účtem s administrátorskými právy.</p>

        @if (error_message)
            <div class="auth-alert">{{ error_message }}</div>
        @endif

        {{ login_form }}

        <p class="auth-meta">Výchozí účet po instalaci: admin / change-me-now</p>
    </div>
</section>
