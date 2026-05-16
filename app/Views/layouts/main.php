    {{ view('partials/head', head ?? [] ) }}
<body>
    {{ view('partials/header', header ?? [] ) }}
    <main>
        {{ content }}
    </main>
    {{ view ('partials/footer', footer ?? []) }}
</body>
</html>