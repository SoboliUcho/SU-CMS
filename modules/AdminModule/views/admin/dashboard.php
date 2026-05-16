<style>
.admin-shell {
    background: linear-gradient(165deg, #eef3f8 0%, #f6f2ea 55%, #f5faf7 100%);
    min-height: calc(100vh - 100px);
    padding: 24px;
}
.admin-topbar {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}
.admin-topbar h1 {
    margin: 0;
    font-size: 1.7rem;
    color: #0f2233;
}
.admin-topbar p {
    margin: 4px 0 0;
    color: #5b6b7a;
}
.topbar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.inline-form {
    margin: 0;
}
.btn {
    border: 1px solid #1f5f82;
    border-radius: 10px;
    background: #1f5f82;
    color: #fff;
    font-weight: 700;
    padding: 9px 12px;
    cursor: pointer;
}
.btn.danger {
    border-color: #8f2f2f;
    background: #8f2f2f;
}
.btn.secondary {
    border-color: #9fb0bf;
    background: #fff;
    color: #203548;
}
.alert {
    border-radius: 10px;
    padding: 10px 12px;
    margin: 0 0 14px;
}
.alert.ok {
    background: #e9f8ef;
    border: 1px solid #97d1a8;
    color: #1f6a37;
}
.alert.error {
    background: #ffeaea;
    border: 1px solid #efb0b0;
    color: #8e2b2b;
}
.module-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
}
.panel {
    background: #fff;
    border: 1px solid #d9e3ec;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(16, 37, 55, 0.09);
    padding: 16px;
    margin-bottom: 16px;
}
.panel h2 {
    margin: 0 0 12px;
    color: #13293a;
}
.create-user-form {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    align-items: end;
}
.create-user-form .hidden-field {
    display: none;
}
.create-user-form .submit-field {
    align-self: end;
}
.create-user-form label {
    display: block;
    font-weight: 600;
    color: #213646;
    margin-bottom: 4px;
}
.create-user-form input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #becad6;
    border-radius: 10px;
    padding: 9px 10px;
}
.users-table-wrap {
    overflow-x: auto;
}
.users-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}
.users-table th,
.users-table td {
    border-bottom: 1px solid #e3ebf3;
    text-align: left;
    padding: 8px 10px;
    vertical-align: top;
}
.user-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.module-card {
    background: #fff;
    border: 1px solid #d9e3ec;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(16, 37, 55, 0.09);
    padding: 16px;
}
.module-card h3 {
    margin: 0 0 6px;
    color: #13293a;
}
.module-card .version {
    margin: 0 0 12px;
    color: #4f6579;
    font-size: 0.92rem;
}
.module-card details {
    margin-bottom: 12px;
}
.module-card pre {
    background: #f3f7fb;
    border: 1px solid #d6e1ea;
    border-radius: 10px;
    padding: 10px;
    max-height: 180px;
    overflow: auto;
    font-size: 0.8rem;
}
.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.actions form {
    margin: 0;
}
.actions .hidden-field,
.user-actions .hidden-field,
.topbar-actions .hidden-field {
    display: none;
}
.actions .submit-field,
.user-actions .submit-field,
.topbar-actions .submit-field {
    margin: 0;
}
@media (max-width: 720px) {
    .admin-shell {
        padding: 16px;
    }
    .admin-topbar h1 {
        font-size: 1.35rem;
    }
}
</style>

<section class="admin-shell">
    <header class="admin-topbar">
        <div>
            <h1>Administrace modulů</h1>
            <p>Přihlášený uživatel: {{ username }}</p>
        </div>

        <div class="topbar-actions">
            <a class="btn secondary" href="{{ base_url }}/auth/status">Auth status JSON</a>
            {{ logout_form }}
        </div>
    </header>

    @if (flash_message)
        <div class="alert ok">{{ flash_message }}</div>
    @endif

    @if (flash_error)
        <div class="alert error">{{ flash_error }}</div>
    @endif

    <section class="module-grid">
        {{ module_cards }}
    </section>

    <section id="users" class="panel">
        <h2>Správa uživatelů</h2>

        {{ create_user_form }}

        {{ users_table }}
    </section>
</section>
