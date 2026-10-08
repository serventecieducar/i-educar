<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>i-Educar — a Gestão da Rede Municipal</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #143044;
            --muted: #4d6270;
            --line: #d5e0e6;
            --paper: #f4f7f6;
            --card: #ffffff;
            --accent: #0f6e78;
            --accent-dark: #0b545c;
            --sand: #f6efe4;
            --sun: #e8a317;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            color: var(--ink);
            background: var(--paper);
        }

        a { color: inherit; }

        img { max-width: 100%; display: block; }

        .wrap {
            width: min(1180px, calc(100% - 2rem));
            margin: 0 auto;
        }

        body { padding-top: 72px; }

        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 20;
            background: rgba(244, 247, 246, 0.94);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(8px);
        }

        .site-header .wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            min-height: 72px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            font-size: 1.2rem;
        }

        .mark {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--accent);
            display: grid;
            place-items: center;
        }

        .mark svg { width: 24px; height: 24px; }

        .brand .word span { color: var(--accent); }

        .tag {
            background: #e5f3f4;
            color: var(--accent-dark);
            border-radius: 999px;
            padding: 0.35rem 0.8rem;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 2rem;
            align-items: center;
            padding: 1.25rem 0 1.5rem;
        }

        .kicker {
            margin: 0 0 0.6rem;
            color: var(--accent-dark);
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            font-size: 0.78rem;
        }

        h1 {
            margin: 0;
            font-size: clamp(2.2rem, 4.6vw, 3.5rem);
            line-height: 1.05;
            letter-spacing: -0.045em;
        }

        .lead {
            margin: 1rem 0 0;
            color: var(--muted);
            font-size: 1.12rem;
            line-height: 1.65;
        }

        .hero-photo {
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(20, 48, 68, 0.12);
        }

        .hero-photo img { width: 100%; height: 320px; object-fit: cover; }

        .facts {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
            margin: 0 0 1.75rem;
            padding: 0;
            list-style: none;
        }

        .facts li {
            background: var(--ink);
            color: #fff;
            border-radius: 20px;
            padding: 1.15rem 1.2rem;
        }

        .facts li:nth-child(2) { background: var(--accent); }

        .facts li:nth-child(3) { background: #c46b2d; }

        .facts strong {
            display: block;
            font-size: 1.35rem;
            letter-spacing: -0.03em;
            margin: 0.45rem 0 0.25rem;
        }

        .facts span { display: block; line-height: 1.45; font-size: 0.95rem; }

        .icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.16);
            display: grid;
            place-items: center;
        }

        .icon svg { width: 20px; height: 20px; }

        h2 {
            margin: 0 0 0.4rem;
            font-size: clamp(1.6rem, 3vw, 2.1rem);
            letter-spacing: -0.03em;
        }

        .section-lead {
            margin: 0 0 1.2rem;
            color: var(--muted);
            max-width: 62ch;
            line-height: 1.55;
        }

        .jobs {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .job {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 1.1rem 1.15rem 1.2rem;
        }

        .job .icon {
            background: #e5f3f4;
            color: var(--accent-dark);
            margin-bottom: 0.75rem;
        }

        .job h3 { margin: 0 0 0.35rem; font-size: 1.05rem; }

        .job p { margin: 0; color: var(--muted); line-height: 1.5; font-size: 0.95rem; }

        .stories {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .story {
            background: var(--card);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--line);
        }

        .story img { width: 100%; height: 240px; object-fit: cover; }

        .story div { padding: 1.15rem 1.25rem 1.35rem; }

        .story h3 { margin: 0 0 0.4rem; }

        .story p { margin: 0; color: var(--muted); line-height: 1.55; }

        .path {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.8rem;
            margin-bottom: 1.75rem;
            padding: 0;
            list-style: none;
        }

        .path li {
            background: var(--sand);
            border-radius: 18px;
            padding: 1rem;
        }

        .step {
            display: inline-grid;
            place-items: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: var(--sun);
            color: #3d2a00;
            font-weight: 800;
            margin-bottom: 0.55rem;
        }

        .path strong { display: block; margin-bottom: 0.25rem; }

        .path span { color: var(--muted); font-size: 0.92rem; line-height: 1.4; }

        .cities {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .city {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.85rem 1rem;
            align-items: center;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 1rem 1.1rem;
            text-decoration: none;
        }

        .city:hover { border-color: var(--accent); }

        .badge {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: #e5f3f4;
            color: var(--accent-dark);
            display: grid;
            place-items: center;
            font-weight: 800;
            letter-spacing: 0.04em;
            font-size: 1.15rem;
        }

        .city h3 { margin: 0; font-size: 1.45rem; letter-spacing: -0.03em; }

        .city p { margin: 0.15rem 0 0; color: var(--muted); }

        .ibge {
            justify-self: end;
            text-align: right;
            background: var(--sand);
            border-radius: 14px;
            padding: 0.45rem 0.7rem;
            min-width: 7.5rem;
        }

        .ibge small {
            display: block;
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }

        .ibge strong {
            display: block;
            font-size: 1.05rem;
            letter-spacing: 0.03em;
        }

        .enter {
            grid-column: 2 / -1;
            justify-self: start;
            color: var(--accent-dark);
            font-weight: 700;
        }

        .site-footer {
            background: var(--ink);
            color: #d5e3ea;
            margin-top: 1rem;
        }

        .credits {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr;
            gap: 1.5rem 2.5rem;
            padding: 2rem 0 1.4rem;
            align-items: start;
        }

        .foot-label {
            margin: 0 0 0.35rem;
            color: #8fb0b8;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .credits strong {
            display: block;
            color: #fff;
            font-size: 1.15rem;
            letter-spacing: -0.02em;
            margin-bottom: 0.25rem;
        }

        .credits p, .credits a {
            margin: 0;
            display: block;
            color: #d5e3ea;
            line-height: 1.55;
            font-size: 0.95rem;
            text-decoration: none;
        }

        .credits a:hover { color: #fff; }

        .foot-brand p { max-width: 28rem; margin-top: 0.35rem; }

        .copy {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            padding: 0.9rem 0 1.2rem;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .copy p { margin: 0; }

        .copy-main { max-width: 36rem; }

        .copy-license { max-width: 28rem; text-align: right; }

        @media (max-width: 1100px) {
            .jobs { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .path { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 800px) {
            .hero, .facts, .stories, .cities { grid-template-columns: 1fr; }
            .credits { grid-template-columns: 1fr; gap: 1.25rem; }
            .jobs, .path { grid-template-columns: 1fr; }
            .hero-photo img { height: 220px; }
            .story img { height: 200px; }
            .city { grid-template-columns: auto 1fr; }
            .ibge { justify-self: start; text-align: left; }
            .enter { grid-column: 1 / -1; }
            .copy { flex-direction: column; }
            .copy-license { text-align: left; }
            .tag { display: none; }
            body { padding-top: 72px; }
        }

        @media (max-width: 480px) {
            h1 { font-size: 2rem; }
            .wrap { width: min(1180px, calc(100% - 1.25rem)); }
            .facts li, .job, .path li { padding: 0.9rem; }
            .badge { width: 56px; height: 56px; font-size: 1rem; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="wrap">
            <div class="brand">
                <span class="mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8">
                        <path d="M3 10.5 12 4l9 6.5"/>
                        <path d="M6 10v9h12v-9"/>
                        <path d="M10 19v-5h4v5"/>
                    </svg>
                </span>
                <span class="word">i-<span>Educar</span></span>
            </div>
            <div class="tag">Para a escola pública municipal</div>
        </div>
    </header>

    <div class="wrap">
        <section class="hero">
            <div>
                <p class="kicker">O que é o i-Educar</p>
                <h1>O caderno da rede municipal, num só lugar.</h1>
                <p class="lead">
                    O i-Educar é o sistema que a secretaria de educação usa para cuidar da vida escolar:
                    quem estuda, em qual escola, em qual turma, se veio à aula e como foi o ano.
                    É um programa livre: a prefeitura pode usar sem pagar licença de software.
                
                </p>
            </div>
            <div class="hero-photo">
                <img src="{{ url('/images/landing/hero-escola.jpg') }}" alt="Professora recebe crianças no pátio de uma escola municipal, com uma mãe chegando de mãos dadas com o filho.">
            </div>
        </section>

        <ul class="facts">
            <li>
                <span class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8"><path d="M4 19V9l8-5 8 5v10"/><path d="M9 19v-6h6v6"/></svg>
                </span>
                <strong>Feito para a prefeitura</strong>
                <span>Secretaria, escola e professor trabalham no mesmo cadastro, cada um no que é da sua função.</span>
            </li>
            <li>
                <span class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>
                </span>
                <strong>Cada cidade no seu cantinho</strong>
                <span>Aluno, escola e documento de um município não aparecem no outro. Escolha a sua cidade abaixo.</span>
            </li>
        </ul>

        <h2>O que a secretaria faz por aqui</h2>
        <p class="section-lead">Em linguagem do dia a dia, sem precisar saber de informática.</p>
        <div class="jobs">
            <article class="job">
                <div class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3"/><path d="M5 19c1.5-3 4-4.5 7-4.5S17.5 16 19 19"/></svg>
                </div>
                <h3>Cadastrar o aluno</h3>
                <p>Nome, nascimento, endereço, responsável e a escola em que estuda. Um cadastro só, usado o ano inteiro.</p>
            </article>
            <article class="job">
                <div class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 5h14v14H5z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
                </div>
                <h3>Matricular e enturmar</h3>
                <p>A matrícula entra na série certa e a criança vai para a turma do turno: manhã, tarde ou integral.</p>
            </article>
            <article class="job">
                <div class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 4h10v16H7z"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>
                </div>
                <h3>Chamada, nota e falta</h3>
                <p>O professor registra quem veio, a nota e a falta. A secretaria acompanha sem recolher papel de cada sala.</p>
            </article>
            <article class="job">
                <div class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/><path d="M8 13h8M8 17h6"/></svg>
                </div>
                <h3>Boletim, histórico e declaração</h3>
                <p>Documentos da vida escolar saem do próprio cadastro: boletim, histórico, atestado de matrícula e de frequência.</p>
            </article>
            <article class="job">
                <div class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5h16v14"/><path d="M8 19v-6h3v6M13 19V9h3v10"/></svg>
                </div>
                <h3>Censo Escolar</h3>
                <p>Os dados que o governo federal pede todo ano, o Educacenso, são preparados a partir do que já está lançado na rede.</p>
            </article>
            <article class="job">
                <div class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="6"/><path d="M16 16l4 4"/><path d="M9 11h4M11 9v4"/></svg>
                </div>
                <h3>Análise de dados</h3>
                <p>Com o Censo fechado, analisamos os números da rede: quantos alunos, em quais escolas e onde a matrícula pede atenção.</p>
            </article>
            <article class="job">
                <div class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/></svg>
                </div>
                <h3>Consultoria financeira</h3>
                <p>Esses mesmos números orientam o dinheiro da educação, como o FUNDEB. A consultoria ajuda a secretaria a entender o que a rede recebe e o que precisa cuidar.</p>
            </article>
            <article class="job">
                <div class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>
                </div>
                <h3>Pré-matrícula pela internet</h3>
                <p>Quando a prefeitura abre o processo, a família se inscreve sem ir à secretaria só para pegar ficha.</p>
            </article>
        </div>

        <div class="stories">
            <article class="story">
                <img src="{{ url('/images/landing/secretaria.jpg') }}" alt="Servidora da secretaria mostra um documento para um pai e uma criança, com o mapa da cidade na parede.">
                <div>
                    <h3>Na secretaria</h3>
                    <p>Quem atende a família encontra a matrícula, a escola e o documento no mesmo lugar. Menos fila, menos papel repetido, menos cadastro em planilha solta.</p>
                </div>
            </article>
            <article class="story">
                <img src="{{ url('/images/landing/sala.jpg') }}" alt="Professora diante do quadro, com alunos levantando a mão em uma sala de aula.">
                <div>
                    <h3>Na sala de aula</h3>
                    <p>A turma do dia já está montada: lista de alunos, chamada e o que foi avaliado. O que o professor lança vira boletim e histórico no fim do ano.</p>
                </div>
            </article>
        </div>

        <h2>O caminho de um aluno</h2>
        <p class="section-lead">Do primeiro papel até o fim do ano letivo.</p>
        <ol class="path">
            <li>
                <span class="step">1</span>
                <strong>Chega na rede</strong>
                <span>A família procura a secretaria ou faz a pré-matrícula, quando ela está aberta.</span>
            </li>
            <li>
                <span class="step">2</span>
                <strong>Ganha uma turma</strong>
                <span>A escola confirma a série, o turno e a vaga. O aluno passa a constar na lista.</span>
            </li>
            <li>
                <span class="step">3</span>
                <strong>O ano acontece</strong>
                <span>Faltas, notas e ocorrências ficam registradas ao longo dos bimestres.</span>
            </li>
            <li>
                <span class="step">4</span>
                <strong>O ano fecha</strong>
                <span>Aprovado, retido ou transferido. O histórico acompanha a criança se ela mudar de escola.</span>
            </li>
        </ol>

        <h2>Escolha a sua cidade</h2>
        <p class="section-lead">Cada cartão abre a rede daquela prefeitura. O login é o da secretaria da cidade, não desta página.</p>
        <div class="cities">
            @foreach ($tenants as $tenant)
                <a class="city" href="{{ request()->getScheme() }}://{{ $tenant['host'] }}">
                    <span class="badge">{{ $tenant['uf'] }}</span>
                    <span>
                        <h3>{{ $tenant['city'] }}</h3>
                        <p>Rede municipal de {{ $tenant['city'] }}/{{ $tenant['uf'] }}</p>
                    </span>
                    <span class="ibge">
                        <small>IBGE</small>
                        <strong>{{ $tenant['ibge'] }}</strong>
                    </span>
                    <span class="enter">Entrar nesta cidade</span>
                </a>
            @endforeach
        </div>

    </div>

    <footer class="site-footer">
        <div class="wrap credits">
            <div class="foot-brand">
                <strong>i-Educar</strong>
                <p>Software livre de gestão escolar para a rede municipal. Esta página só indica a cidade. O cadastro dos alunos fica na prefeitura.</p>
            </div>
            <div>
                <p class="foot-label">Desenvolvimento</p>
                <strong>Buriti</strong>
                <p>Jader Gabriel</p>
                <a href="https://github.com/JaderGabriel">github.com/JaderGabriel</a>
                <a href="mailto:jadergabriel8@gmail.com">jadergabriel8@gmail.com</a>
            </div>
            <div>
                <p class="foot-label">Assessoria</p>
                <strong>Serventec</strong>
                <p>Serventec Assessoria</p>
                <a href="https://serventecassessoria.com.br">serventecassessoria.com.br</a>
                <a href="mailto:contato@serventecassessoria.com.br">contato@serventecassessoria.com.br</a>
            </div>
        </div>
        <div class="wrap copy">
            <p class="copy-main">© {{ date('Y') }} Buriti. Todos os direitos reservados.</p>
            <p class="copy-license">i-Educar, licença GPL-2.0 ou posterior. Núcleo original: Portábilis.</p>
        </div>
    </footer>
</body>
</html>
