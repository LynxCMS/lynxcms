<?php

declare(strict_types=1);

/*
 * Atlas Training's demo content, in English and Portuguese. Texts are ['en' => …, 'pt' => …];
 * AtlasContentSeeder turns them into pages.
 */

return [
    'instructors' => [
        'ana-ribeiro' => ['pt' => 'ana-ribeiro', 'title' => 'Ana Ribeiro',
            'summary' => ['en' => 'Yoga teacher (E-RYT 500) and lead trainer.', 'pt' => 'Professora de yoga (E-RYT 500) e formadora principal.'],
            'body' => ['en' => '<p>Ana has taught yoga for fifteen years and trained over 400 teachers.</p>', 'pt' => '<p>A Ana ensina yoga há quinze anos e formou mais de 400 professores.</p>']],
        'tomas-ferreira' => ['pt' => 'tomas-ferreira', 'title' => 'Tomás Ferreira',
            'summary' => ['en' => 'Sports nutritionist and researcher.', 'pt' => 'Nutricionista desportivo e investigador.'],
            'body' => ['en' => '<p>Tomás works with athletes and teaches evidence-based nutrition.</p>', 'pt' => '<p>O Tomás trabalha com atletas e ensina nutrição baseada em evidência.</p>']],
        'joana-matos' => ['pt' => 'joana-matos', 'title' => 'Joana Matos',
            'summary' => ['en' => 'Pilates and movement teacher.', 'pt' => 'Professora de pilates e movimento.'],
            'body' => ['en' => '<p>Joana trains pilates instructors and leads our movement courses.</p>', 'pt' => '<p>A Joana forma instrutores de pilates e orienta os cursos de movimento.</p>']],
    ],

    'locations' => [
        'lisbon' => ['pt' => 'lisboa', 'title' => ['en' => 'Lisbon studio', 'pt' => 'Estúdio de Lisboa'], 'street' => 'Rua da Prata 120', 'city' => 'Lisboa', 'postal_code' => '1100-420'],
        'porto' => ['pt' => 'porto', 'title' => ['en' => 'Porto studio', 'pt' => 'Estúdio do Porto'], 'street' => 'Rua de Cedofeita 300', 'city' => 'Porto', 'postal_code' => '4050-174'],
    ],

    'courses' => [
        'yoga-teacher-training' => ['pt' => 'formacao-professores-yoga', 'level' => 'advanced', 'hours' => 200, 'price' => 2400, 'instructors' => ['ana-ribeiro'],
            'title' => ['en' => 'Yoga teacher training', 'pt' => 'Formação de professores de yoga'],
            'summary' => ['en' => 'Two hundred hours to teach yoga with confidence, certified by Yoga Alliance.', 'pt' => 'Duzentas horas para ensinar yoga com confiança, com certificação Yoga Alliance.'],
            'curriculum' => [['en' => 'Anatomy and alignment', 'pt' => 'Anatomia e alinhamento'], ['en' => 'Sequencing a class', 'pt' => 'Construir uma aula'], ['en' => 'Teaching practice', 'pt' => 'Prática de ensino']]],
        'mindful-movement' => ['pt' => 'movimento-consciente', 'level' => 'beginner', 'hours' => 12, 'price' => 180, 'instructors' => ['joana-matos'],
            'title' => ['en' => 'Mindful movement', 'pt' => 'Movimento consciente'],
            'summary' => ['en' => 'Gentle weekly classes to move better and feel calmer.', 'pt' => 'Aulas semanais suaves para se mexer melhor e sentir mais calma.'],
            'curriculum' => [['en' => 'Breathing and posture', 'pt' => 'Respiração e postura'], ['en' => 'Mobility at home', 'pt' => 'Mobilidade em casa']]],
        'sports-nutrition' => ['pt' => 'nutricao-desportiva', 'level' => 'intermediate', 'hours' => 24, 'price' => 420, 'instructors' => ['tomas-ferreira'],
            'title' => ['en' => 'Sports nutrition', 'pt' => 'Nutrição desportiva'],
            'summary' => ['en' => 'Plan meals for training and recovery, from the research to the plate.', 'pt' => 'Planeie refeições para treino e recuperação, da investigação ao prato.'],
            'curriculum' => [['en' => 'Energy and macronutrients', 'pt' => 'Energia e macronutrientes'], ['en' => 'Hydration', 'pt' => 'Hidratação'], ['en' => 'Supplements', 'pt' => 'Suplementos']]],
        'pilates-mat-instructor' => ['pt' => 'instrutor-pilates-solo', 'level' => 'intermediate', 'hours' => 60, 'price' => 950, 'instructors' => ['joana-matos'],
            'title' => ['en' => 'Pilates mat instructor', 'pt' => 'Instrutor de pilates solo'],
            'summary' => ['en' => 'Learn the full mat repertoire and how to teach it to groups.', 'pt' => 'Aprenda o repertório completo de solo e como o ensinar a grupos.'],
            'curriculum' => [['en' => 'The classical repertoire', 'pt' => 'O repertório clássico'], ['en' => 'Cueing and corrections', 'pt' => 'Indicações e correções']]],
        'breathwork-foundations' => ['pt' => 'fundamentos-respiracao', 'level' => 'beginner', 'hours' => 8, 'price' => 120, 'instructors' => ['ana-ribeiro'],
            'title' => ['en' => 'Breathwork foundations', 'pt' => 'Fundamentos de respiração'],
            'summary' => ['en' => 'A weekend of breathing techniques for focus and sleep.', 'pt' => 'Um fim de semana de técnicas de respiração para foco e sono.'],
            'curriculum' => [['en' => 'How breathing works', 'pt' => 'Como funciona a respiração'], ['en' => 'Daily practice', 'pt' => 'Prática diária']]],
        'personal-trainer-certificate' => ['pt' => 'certificado-personal-trainer', 'level' => 'advanced', 'hours' => 120, 'price' => 1600, 'instructors' => ['tomas-ferreira', 'joana-matos'],
            'title' => ['en' => 'Personal trainer certificate', 'pt' => 'Certificado de personal trainer'],
            'summary' => ['en' => 'Everything you need to train clients safely and build your own practice.', 'pt' => 'Tudo o que precisa para treinar clientes em segurança e criar o seu negócio.'],
            'curriculum' => [['en' => 'Assessment', 'pt' => 'Avaliação'], ['en' => 'Programming', 'pt' => 'Programação'], ['en' => 'Running your business', 'pt' => 'Gerir o seu negócio']]],
    ],

    'home' => [
        'title' => ['en' => 'Learn to teach wellness', 'pt' => 'Aprenda a ensinar bem-estar'],
        'summary' => ['en' => 'Certified courses in yoga, pilates and nutrition, in Lisbon, Porto and online.', 'pt' => 'Cursos certificados de yoga, pilates e nutrição, em Lisboa, no Porto e online.'],
        'highlights' => [
            ['flower-2', ['en' => 'Certified', 'pt' => 'Certificados'], ['en' => 'Recognised by Yoga Alliance and the national registry.', 'pt' => 'Reconhecidos pela Yoga Alliance e pelo registo nacional.']],
            ['users', ['en' => 'Small groups', 'pt' => 'Grupos pequenos'], ['en' => 'Never more than 16 people, so you get feedback every class.', 'pt' => 'Nunca mais de 16 pessoas, para ter feedback em todas as aulas.']],
            ['calendar-heart', ['en' => 'Flexible dates', 'pt' => 'Datas flexíveis'], ['en' => 'Weekend and evening sessions all year.', 'pt' => 'Sessões ao fim de semana e à noite durante todo o ano.']],
        ],
        'stats' => [['2,400+', ['en' => 'graduates', 'pt' => 'formandos']], ['15', ['en' => 'years teaching', 'pt' => 'anos a ensinar']], ['4.9', ['en' => 'average rating', 'pt' => 'avaliação média']]],
        'featured' => ['yoga-teacher-training', 'sports-nutrition', 'pilates-mat-instructor'],
        'steps' => [
            [['en' => 'Choose a course', 'pt' => 'Escolha um curso'], ['en' => 'Compare levels, hours and dates.', 'pt' => 'Compare níveis, horas e datas.']],
            [['en' => 'Book a session', 'pt' => 'Reserve uma sessão'], ['en' => 'Pay online and get your place at once.', 'pt' => 'Pague online e garanta o lugar de imediato.']],
            [['en' => 'Learn and practise', 'pt' => 'Aprenda e pratique'], ['en' => 'Materials wait for you in your members area.', 'pt' => 'Os materiais esperam por si na área de membros.']],
        ],
        'testimonials' => [
            [['en' => 'The best decision I made for my career.', 'pt' => 'A melhor decisão que tomei na minha carreira.'], 'Rita S.', 5],
            [['en' => 'Clear, practical and warm teaching.', 'pt' => 'Ensino claro, prático e acolhedor.'], 'Miguel A.', 5],
        ],
        'faqs' => [
            [['en' => 'Do I need experience?', 'pt' => 'Preciso de experiência?'], ['en' => 'Beginner courses need none. Each course page says what it expects.', 'pt' => 'Os cursos de iniciação não exigem nenhuma. Cada curso diz o que espera.']],
            [['en' => 'Can I pay in instalments?', 'pt' => 'Posso pagar em prestações?'], ['en' => 'Yes, for courses over 500 EUR. Ask us before booking.', 'pt' => 'Sim, para cursos acima de 500 EUR. Fale connosco antes de reservar.']],
        ],
    ],
];
