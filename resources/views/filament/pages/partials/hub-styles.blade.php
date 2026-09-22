<style>
/* ================================================================
   Hub Page v2 — Unified Design System
   Shared partial — included by all *HubPage blade views
   ================================================================ */

/* ── Hero ────────────────────────────────────────────────────── */
.hubv2-hero {
    border-radius: 20px;
    padding: 2rem 2.5rem;
    margin-bottom: 1.75rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    position: relative;
    overflow: hidden;
}
.hubv2-hero::before {
    content: '';
    position: absolute;
    right: -50px;
    top: -50px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255,255,255,.2);
    pointer-events: none;
}
.hubv2-hero::after {
    content: '';
    position: absolute;
    left: 38%;
    bottom: -70px;
    width: 190px;
    height: 190px;
    border-radius: 50%;
    background: rgba(255,255,255,.12);
    pointer-events: none;
}
.hubv2-hero-icon {
    width: 72px;
    height: 72px;
    border-radius: 18px;
    background: rgba(255,255,255,.85);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 18px rgba(0,0,0,.1);
    position: relative;
    z-index: 1;
}
.hubv2-hero-icon svg { width: 36px; height: 36px; }
.hubv2-hero-body {
    flex: 1;
    min-width: 0;
    position: relative;
    z-index: 1;
}
.hubv2-hero-badge {
    display: inline-flex;
    align-items: center;
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    background: rgba(255,255,255,.45);
    border: 1px solid rgba(255,255,255,.7);
    border-radius: 20px;
    padding: .2rem .85rem;
    color: rgba(15,23,42,.6);
    margin-bottom: .5rem;
}
.hubv2-hero-title {
    font-size: 1.65rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 .4rem;
    line-height: 1.2;
}
.hubv2-hero-subtitle {
    font-size: .875rem;
    color: rgba(15,23,42,.6);
    margin: 0;
    line-height: 1.55;
    max-width: 520px;
}
.hubv2-hero-meta {
    flex-shrink: 0;
    text-align: center;
    position: relative;
    z-index: 1;
    padding-left: 1.5rem;
}
.hubv2-hero-meta-num {
    display: block;
    font-size: 3.25rem;
    font-weight: 900;
    line-height: 1;
    color: rgba(0,0,0,.12);
}
.hubv2-hero-meta-lbl {
    display: block;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .12em;
    color: rgba(0,0,0,.32);
    margin-top: .15rem;
}

/* ── Section header ──────────────────────────────────────────── */
.hubv2-sh {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: .9rem;
    margin-top: 1.6rem;
}
.hubv2-sh-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--hub-c1, #3b82f6);
    flex-shrink: 0;
}
.hubv2-sh-name {
    font-size: .78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: #374151;
    white-space: nowrap;
}
.hubv2-sh-rule {
    flex: 1;
    height: 1px;
    background: #e5e7eb;
}

/* ── Cards grid ──────────────────────────────────────────────── */
.hubv2-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(256px, 1fr));
    gap: .875rem;
    margin-bottom: .5rem;
}

/* ── Single card ─────────────────────────────────────────────── */
.hubv2-card {
    display: flex;
    align-items: center;
    gap: .9rem;
    padding: 1.05rem 1.1rem;
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    text-decoration: none !important;
    color: inherit;
    position: relative;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    overflow: hidden;
}
.hubv2-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3.5px;
    background: var(--hub-c1, #3b82f6);
    opacity: 0;
    transition: opacity .2s ease;
}
.hubv2-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(0,0,0,.09);
    border-color: var(--hub-border, #93c5fd);
}
.hubv2-card:hover::before { opacity: 1; }

/* card icon */
.hubv2-ci {
    width: 42px;
    height: 42px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.hubv2-ci svg { width: 21px; height: 21px; }

/* card body */
.hubv2-cb { flex: 1; min-width: 0; }
.hubv2-cl {
    display: block;
    font-weight: 700;
    font-size: .875rem;
    color: #1e293b;
    line-height: 1.3;
}
.hubv2-cd {
    display: block;
    font-size: .75rem;
    color: #94a3b8;
    margin-top: .2rem;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* card arrow */
.hubv2-ca {
    color: #d1d5db;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    transition: color .2s, transform .2s;
}
.hubv2-ca svg { width: 15px; height: 15px; }
.hubv2-card:hover .hubv2-ca {
    color: var(--hub-c1, #3b82f6);
    transform: translateX(4px);
}

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 640px) {
    .hubv2-hero { padding: 1.25rem; flex-wrap: wrap; gap: 1rem; }
    .hubv2-hero-title { font-size: 1.3rem; }
    .hubv2-hero-meta { display: none; }
    .hubv2-grid { grid-template-columns: 1fr; }
}
</style>
