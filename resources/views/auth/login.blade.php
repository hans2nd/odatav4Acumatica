<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login — PL Sync Engine</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Outfit', sans-serif; }

        /* Premium Mesh Gradient Background */
        .mesh-bg {
            background-color: #030712;
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,30%,0.2) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,30%,0.2) 0, transparent 50%);
            background-attachment: fixed;
            position: relative;
            overflow: hidden;
        }

        .mesh-bg::before {
            content: '';
            position: absolute;
            inset: -50%;
            background: 
                radial-gradient(ellipse at 20% 30%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                radial-gradient(ellipse at 80% 70%, rgba(14, 165, 233, 0.15) 0%, transparent 40%);
            animation: slowSpin 30s linear infinite;
            z-index: 0;
            pointer-events: none;
        }

        @keyframes slowSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Glassmorphism Card */
        .glass-panel {
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 
                0 4px 6px -1px rgba(0, 0, 0, 0.1), 
                0 24px 48px -12px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        /* Input Styling */
        .input-premium {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f8fafc;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        .input-premium:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: rgba(99, 102, 241, 0.6);
            box-shadow: 
                inset 0 2px 4px rgba(0,0,0,0.2),
                0 0 0 4px rgba(99, 102, 241, 0.15);
            outline: none;
        }

        .input-premium::placeholder {
            color: #64748b;
        }

        /* Button Styling */
        .btn-glow {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
            overflow: hidden;
        }

        .btn-glow::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, #6366f1 0%, #60a5fa 100%);
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .btn-glow:hover::before {
            opacity: 1;
        }

        .btn-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
        }

        .btn-glow:active {
            transform: translateY(0);
        }

        /* Grid Pattern Overlay */
        .grid-overlay {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            background-size: 32px 32px;
            z-index: 0;
            pointer-events: none;
        }

        .fade-up {
            animation: fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .fade-up-delay-1 { animation-delay: 0.1s; }
        .fade-up-delay-2 { animation-delay: 0.2s; }
        .fade-up-delay-3 { animation-delay: 0.3s; }

        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="mesh-bg min-h-screen flex items-center justify-center p-4 sm:p-8">
    <div class="grid-overlay"></div>

    <div class="relative z-10 w-full max-w-md">
        {{-- Logo / Brand --}}
        <div class="flex flex-col items-center justify-center mb-8 fade-up">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-sky-500 p-[1px] shadow-xl shadow-indigo-500/20 mb-5 relative group">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500 to-sky-500 blur-md opacity-40 group-hover:opacity-60 transition-opacity"></div>
                <div class="relative w-full h-full bg-slate-900 rounded-[15px] flex items-center justify-center overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/20 to-sky-500/20"></div>
                    <span class="material-symbols-outlined text-white text-3xl drop-shadow-md z-10" style="font-variation-settings: 'FILL' 1;">sync_alt</span>
                </div>
            </div>
            <h1 class="text-3xl font-bold text-white tracking-tight">PL Sync Engine</h1>
            <p class="text-indigo-200/70 text-sm mt-1.5 font-medium tracking-wide uppercase">Acumatica OData Integration</p>
        </div>

        {{-- Login Card --}}
        <div class="glass-panel rounded-3xl p-8 sm:p-10 fade-up fade-up-delay-1 relative overflow-hidden">
            <!-- Decorative light beam -->
            <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-indigo-400/50 to-transparent"></div>

            <div class="text-center mb-8">
                <h2 class="text-xl font-semibold text-white">Welcome back</h2>
                <p class="text-slate-400 text-sm mt-1">Please enter your credentials</p>
            </div>

            {{-- Error Messages --}}
            @if ($errors->any())
                <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 mb-6 backdrop-blur-sm flex items-start gap-3 fade-up">
                    <span class="material-symbols-outlined text-rose-400 text-[20px] shrink-0 mt-0.5">error</span>
                    <span class="text-rose-200 text-sm font-medium leading-relaxed">{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ url('/login') }}" id="login-form">
                @csrf

                {{-- Email --}}
                <div class="mb-5 fade-up fade-up-delay-2">
                    <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-widest mb-2 ml-1">Email Address</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-[20px] group-focus-within:text-indigo-400 transition-colors">mail</span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="email"
                            class="input-premium w-full pl-12 pr-4 py-3.5 rounded-2xl text-sm font-medium"
                            placeholder="name@company.com"
                        />
                    </div>
                </div>

                {{-- Password --}}
                <div class="mb-6 fade-up fade-up-delay-2">
                    <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-widest mb-2 ml-1">Password</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-[20px] group-focus-within:text-indigo-400 transition-colors">lock</span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            class="input-premium w-full pl-12 pr-12 py-3.5 rounded-2xl text-sm font-medium"
                            placeholder="••••••••"
                        />
                        <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors p-1 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                            <span id="password-toggle-icon" class="material-symbols-outlined text-[20px]">visibility_off</span>
                        </button>
                    </div>
                </div>

                {{-- Remember Me --}}
                <div class="flex items-center justify-between mb-8 fade-up fade-up-delay-3">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <div class="relative flex items-center justify-center">
                            <input type="checkbox" name="remember" class="peer appearance-none w-5 h-5 rounded-md border border-slate-600 bg-slate-800/50 checked:bg-indigo-500 checked:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:ring-offset-2 focus:ring-offset-slate-900 transition-all cursor-pointer">
                            <span class="material-symbols-outlined absolute text-white text-[14px] opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity" style="font-variation-settings: 'FILL' 1;">check</span>
                        </div>
                        <span class="text-sm font-medium text-slate-300 group-hover:text-white transition-colors">Keep me signed in</span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit" id="btn-submit" class="btn-glow fade-up fade-up-delay-3 w-full py-3.5 px-4 rounded-2xl text-white font-semibold text-sm tracking-wide flex items-center justify-center gap-2">
                    <span>Secure Sign In</span>
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </form>
        </div>

        {{-- Footer --}}
        <p class="text-center text-slate-500 text-xs mt-8 font-medium fade-up fade-up-delay-3">
            &copy; {{ date('Y') }} PT Pangan Lestari
        </p>
    </div>

    <script>
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
            btn.innerHTML = '<svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span class="ml-2">Authenticating...</span>';
            btn.classList.add('opacity-90', 'cursor-not-allowed');
        });
    </script>
</body>
</html>
