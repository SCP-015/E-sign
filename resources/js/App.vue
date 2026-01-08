<template>
    <div class="app-container">
        <router-view></router-view>
    </div>
</template>

<style>
/* Global Premium Reset & Variables */
:root {
    --bg-color: #0f172a;
    --text-color: #e2e8f0;
    --primary-color: #38bdf8;
    --accent-color: #818cf8;
    --glass-bg: rgba(30, 41, 59, 0.7);
    --glass-border: rgba(255, 255, 255, 0.1);
}

body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background-color: var(--bg-color);
    color: var(--text-color);
    -webkit-font-smoothing: antialiased;
}

.app-container {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* Glassmorphism Util */
.glass {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
}
</style>
