# React Migration TODO

## Phase 1: Setup (Current)
- [x] Create TODO.md
- [ ] Create frontend/ dir + Vite React + Tailwind + deps
- [ ] Update .gitignore
- [ ] Update router.php for SPA fallback
- [ ] Test dev server + API proxy

## Phase 2: Core Components
- [ ] Auth/Login/Register (useAuth hook, API calls)
- [ ] Layout/Nav/Header (responsive sidebar, themes)
- [ ] Dashboard (stats, charts, recent attendance via API)

## Phase 3: Modules
- [ ] Employees (list, add/edit/import)
- [ ] Payroll (structures, payslips, process)
- [ ] Attendance (scanner, logs)
- [ ] Settings (preferences, notifications)

## Phase 4: Polish
- [ ] TanStack Table for data grids
- [ ] shadcn/ui components (modals, toasts)
- [ ] Error boundaries, loading states
- [ ] Build & PHP integration test

## Commands
```
cd frontend
npm install
npm run dev  # http://localhost:5173
npm run build  # to dist/
```

