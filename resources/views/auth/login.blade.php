<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login — PL Sync Engine</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; }

        .login-bg {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            position: relative;
            overflow: hidden;
        }

        .login-bg::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at 30% 20%, rgba(99, 102, 241, 0.08) 0%, transparent 50%),
                        radial-gradient(ellipse at 70% 80%, rgba(59, 130, 246, 0.06) 0%, transparent 50%);
            animation: bgShift 20s ease-in-out infinite alternate;
        }

        @keyframes bgShift {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-5%, -3%); }
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        .input-field {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            transition: all 0.3s ease;
        }

        .input-field:focus {
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .input-field::placeholder {
            color: rgba(148, 163, 184, 0.6);
        }

        .btn-login {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .sample-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .sample-card:hover {
            background: rgba(99, 102, 241, 0.08);
            border-color: rgba(99, 102, 241, 0.2);
            transform: translateX(4px);
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .fade-in-delay {
            animation: fadeIn 0.6s ease-out 0.2s both;
        }

        .fade-in-delay-2 {
            animation: fadeIn 0.6s ease-out 0.4s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dot-pattern {
            background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        .role-badge-admin { background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.2); }
        .role-badge-operator { background: rgba(59, 130, 246, 0.15); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.2); }
        .role-badge-viewer { background: rgba(34, 197, 94, 0.15); color: #86efac; border: 1px solid rgba(34, 197, 94, 0.2); }
    </style>
</head>
<body class="login-bg min-h-screen flex items-center justify-center p-4 dot-pattern">

    <div class="relative z-10 w-full max-w-md">
        {{-- Logo / Brand --}}
        <div class="text-center mb-8 fade-in">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 shadow-lg shadow-indigo-500/20 mb-4">
                <span class="material-symbols-outlined text-white text-3xl" style="font-variation-settings: 'FILL' 1;">sync</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">PL Sync Engine</h1>
            <p class="text-slate-400 text-sm mt-1">Acumatica OData v4 Integration Platform</p>
        </div>

        {{-- Login Card --}}
        <div class="glass-card rounded-2xl p-8 fade-in-delay">
            <h2 class="text-lg font-semibold text-white mb-1">Welcome back</h2>
            <p class="text-slate-400 text-sm mb-6">Sign in to your account to continue</p>

            {{-- Error Messages --}}
            @if ($errors->any())
                <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-3 mb-5">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-400 text-lg">error</span>
                        <span class="text-red-300 text-sm">{{ $errors->first() }}</span>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ url('/login') }}" id="login-form">
                @csrf

                {{-- Email --}}
                <div class="mb-4">
                    <label for="email" class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">Email</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-lg">mail</span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
                            placeholder="your@email.com"
                        />
                    </div>
                </div>

                {{-- Password --}}
                <div class="mb-5">
                    <label for="password" class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">Password</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-lg">lock</span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            class="input-field w-full pl-10 pr-12 py-3 rounded-xl text-sm"
                            placeholder="••••••••"
                        />
                        <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors">
                            <span id="password-toggle-icon" class="material-symbols-outlined text-lg">visibility_off</span>
                        </button>
                    </div>
                </div>

                {{-- Remember Me --}}
                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500/30 focus:ring-offset-0">
                        <span class="text-sm text-slate-400">Remember me</span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit" id="btn-submit" class="btn-login w-full py-3 px-4 rounded-xl text-white font-semibold text-sm flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">login</span>
                    Sign In
                </button>
            </form>
        </div>

        {{-- Sample Accounts --}}
        <div class="mt-6 fade-in-delay-2">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined text-slate-500 text-sm">info</span>
                <span class="text-xs text-slate-500 uppercase tracking-wider font-medium">Demo Accounts</span>
            </div>
            <div class="space-y-2">
                <div class="sample-card rounded-xl px-4 py-3 flex items-center justify-between" onclick="fillCredentials('admin@plsync.com', 'password')">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-red-400 text-sm" style="font-variation-settings: 'FILL' 1;">shield_person</span>
                        </div>
                        <div>
                            <div class="text-sm text-slate-300 font-medium">admin@plsync.com</div>
                            <div class="text-xs text-slate-500">Full system access</div>
                        </div>
                    </div>
                    <span class="role-badge-admin text-xs px-2 py-0.5 rounded-full font-medium">Admin</span>
                </div>

                <div class="sample-card rounded-xl px-4 py-3 flex items-center justify-between" onclick="fillCredentials('operator@plsync.com', 'password')">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-400 text-sm" style="font-variation-settings: 'FILL' 1;">engineering</span>
                        </div>
                        <div>
                            <div class="text-sm text-slate-300 font-medium">operator@plsync.com</div>
                            <div class="text-xs text-slate-500">View, sync & export data</div>
                        </div>
                    </div>
                    <span class="role-badge-operator text-xs px-2 py-0.5 rounded-full font-medium">Operator</span>
                </div>

                <div class="sample-card rounded-xl px-4 py-3 flex items-center justify-between" onclick="fillCredentials('viewer@plsync.com', 'password')">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-green-400 text-sm" style="font-variation-settings: 'FILL' 1;">visibility</span>
                        </div>
                        <div>
                            <div class="text-sm text-slate-300 font-medium">viewer@plsync.com</div>
                            <div class="text-xs text-slate-500">Read-only access</div>
                        </div>
                    </div>
                    <span class="role-badge-viewer text-xs px-2 py-0.5 rounded-full font-medium">Viewer</span>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <p class="text-center text-slate-600 text-xs mt-8">
            &copy; {{ date('Y') }} PT Pangan Lestari — PL Sync Engine
        </p>
    </div>

    <script>
        function fillCredentials(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;

            // Visual feedback
            document.getElementById('email').classList.add('border-indigo-500/50');
            setTimeout(() => document.getElementById('email').classList.remove('border-indigo-500/50'), 800);
        }

        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('password-toggle-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility_off';
            }
        }

        // Loading state on submit
        document.getElementById('login-form').addEventListener('submit', function() {
            const btn = document.getElementById('btn-submit');
            btn.disabled = true;
            btn.innerHTML = '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Signing in...';
        });
    </script>
</body>
</html>
