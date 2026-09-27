<?php
/**
 * Welcome / landing page — Smile Forward Community Dental
 *
 * Everything you're likely to want to change lives in the $config array
 * below: brand name, tagline, colors, stats, and the services list.
 * The HTML further down just loops over these values, so you shouldn't
 * need to touch the markup for routine edits.
 *
 * Assumes BASE_URL is already defined by your app (as in your schedule.php).
 * If this file is included standalone, we fall back to '' so links still work.
 */

if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

$config = [
    'brand'   => 'Smiley',
    'kicker'  => 'A dental home for every neighborhood',
    'heading' => "Smilling because both my oral and financial health are taken care of.",
    'subhead' => "Smile Forward brings honest, judgment-free dental care to families "
               . "who've been priced out or turned away elsewhere — with sliding-scale "
               . "fees, evening hours, and a team that treats every patient like a neighbor.",

    // Hero badge in the middle of the orbit graphic
    'hero_badge' => ['number' => '12k+', 'label' => 'patients treated free or reduced-cost'],
    'hero_chips' => [
        '🦷 Same-week visits',
        '🤝 No one turned away',
    ],

    // Colors — change these to re-theme the whole page
    'colors' => [
        'white'       => '#FFFFFF',
        'lilac'       => '#F3EDFB',
        'lilac2'      => '#E9DEF8',
        'ink'         => '#2B1A3E',
        'purple_deep' => '#3B1F52',
        'purple'      => '#6C3FA0',
        'purple_soft' => '#9868C9',
    ],

    'mission_quote' => "We started Smile Forward because a toothache shouldn't decide "
                      . "whether a kid goes to school or a parent misses a paycheck.",
    'mission_body' => [
        "Dental care is one of the most common reasons people miss work or school in "
            . "underserved communities — and one of the least covered by public insurance. "
            . "We built Smile Forward to close that gap, one appointment at a time.",
        "Every patient pays what they can, not a fixed price list. Our team partners with "
            . "local shelters, schools, and community centers to bring checkups directly to "
            . "the people who need them most, and we keep evening and weekend hours so a day "
            . "off work is never the price of a healthy smile.",
    ],  

    // Stats strip (About Us section)
    'stats' => [
        ['number' => '12,400+', 'label' => 'patients seen since we opened'],
        ['number' => '68%',     'label' => 'treated free or on a sliding scale'],
        ['number' => '9',       'label' => 'community neighborhoods served'],
        ['number' => '0',       'label' => 'patients ever turned away for cost'],
    ],

    // Services grid
    'services' => [
        ['icon' => '🦷', 'title' => 'General & family dentistry', 'body' => "A dental checkup and cleaning is a routine visit to the dentist. It's important to visit the dentist regularly because they can spot problems early, before they become serious issues. During your appointment, our dentist will examine your teeth and gums for any signs of decay or infection that may need treatment. They'll also check for cavities or other problems with your bite."],
        ['icon' => '🧒', 'title' => 'Pediatric care', 'body' => 'Every child under 12 in our service area is seen free for checkups, cleanings, and sealants.'],
        ['icon' => '🚨', 'title' => 'Urgent & emergency visits', 'body' => 'Same-week appointments for pain, infection, or injury — no one waits in pain over cost.'],
        ['icon' => '🪥', 'title' => 'Teeth cleaning', 'body' => 'Teeth cleaning is a professional dental procedure, also known as prophylaxis, that involves the removal of plaque, tartar (calculus), and stains to prevent cavities, gingivitis, and periodontal disease.  Performed by a dental hygienist or dentist, it complements daily home care by addressing buildup in areas that brushing and flossing cannot reach. A standard routine cleaning typically takes 30 to 60 minutes and follows a structured process:'],
        ['icon' => '🦴', 'title' => 'Restorative & oral surgery', 'body' => 'Oral surgery refers to any operation done inside your mouth. Examples include tooth extractions, dental implants and tissue grafts. Dental specialists, who have advanced training in oral surgery, do these procedures. Healing times vary depending on the type of surgery you need.'],
        ['icon' => '🗣️', 'title' => 'Care in your language', 'body' => 'Our team(including our chatbot) speaks the languages of the communities we serve, so nothing gets lost in the chair.'],
    ],

    'cta_heading' => 'Ready when you are — book your visit today.',
    'cta_body'    => "It takes two minutes to schedule, and there's no cost to ask questions or find out what you qualify for.",

    // Where the "Book an appointment" buttons should go
    'book_url' => BASE_URL . '/bookappointment',
];

$c = $config['colors'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= htmlspecialchars($config['brand']) ?> — Community Dental Care</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,500;9..144,600;9..144,700&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --white:<?= $c['white'] ?>;
    --lilac:<?= $c['lilac'] ?>;
    --lilac2:<?= $c['lilac2'] ?>;
    --ink:<?= $c['ink'] ?>;
    --purple-deep:<?= $c['purple_deep'] ?>;
    --purple:<?= $c['purple'] ?>;
    --purple-soft:<?= $c['purple_soft'] ?>;
    --line:rgba(59,31,82,0.14);
    box-sizing:border-box;
    padding-top:env(safe-area-inset-top,0px);
    padding-bottom:env(safe-area-inset-bottom,0px);
  }
  *{box-sizing:border-box;}
  html{scroll-behavior:smooth; scroll-padding-top:76px;}
  body{
    margin:0;
    background:var(--white);
    color:var(--ink);
    font-family:'Public Sans',system-ui,sans-serif;
    -webkit-font-smoothing:antialiased;
  }
  h1,h2,h3{font-family:'Fraunces',Georgia,serif; margin:0; font-weight:600; letter-spacing:-0.01em;}
  p{margin:0;}
  a{color:inherit;}

  .wrap{max-width:1120px; margin:0 auto; padding:0 28px;}

  nav{
    position:sticky; top:0; z-index:20; background:rgba(255,255,255,0.9);
    backdrop-filter:blur(8px); border-bottom:1px solid var(--line);
    padding:18px 28px;
  }
  .nav-inner{max-width:1120px; margin:0 auto; width:100%; display:flex; align-items:center; justify-content:space-between;}
  .brand{display:flex; align-items:center; gap:10px; font-family:'Fraunces',serif; font-weight:600; font-size:1.15rem; color:var(--purple-deep);}
  .brand-mark{
    width:34px; height:34px; border-radius:50%;
    background:var(--purple-deep);
    display:flex; align-items:center; justify-content:center;
    color:var(--white); font-size:1rem;
  }
  .nav-links{display:flex; align-items:center; gap:30px; font-size:0.95rem; font-weight:600;}
  .nav-links a{text-decoration:none; color:var(--ink); opacity:0.75;}
  .nav-links a:hover{opacity:1;}
  .nav-cta{
    background:var(--purple-deep); color:var(--white);
    padding:10px 20px; border-radius:100px; text-decoration:none;
    font-weight:600; font-size:0.9rem; white-space:nowrap;
  }
  @media (max-width:760px){ .nav-links{display:none;} }

  .hero{
    display:grid; grid-template-columns:1.15fr 0.85fr; gap:40px;
    align-items:center; padding:64px 0 70px;
  }
  .kicker{font-size:0.95rem; color:var(--purple); font-weight:600; margin-bottom:14px;}
  .hero h1{font-size:clamp(2.3rem, 5vw, 3.6rem); line-height:1.05; max-width:14ch; color:var(--purple-deep);}
  .hero-sub{margin-top:20px; font-size:1.08rem; line-height:1.6; max-width:42ch; color:#4A3A5E;}
  .hero-actions{display:flex; gap:14px; margin-top:30px; flex-wrap:wrap;}
  .btn-primary{
    background:var(--purple-deep); color:var(--white); padding:15px 26px; border-radius:10px;
    text-decoration:none; font-weight:700; font-size:1rem; border:none; cursor:pointer;
    box-shadow:0 12px 26px rgba(59,31,82,0.28); display:inline-block;
  }
  .btn-ghost{
    background:transparent; color:var(--purple-deep); padding:15px 22px; border-radius:10px;
    text-decoration:none; font-weight:600; font-size:1rem; border:1.5px solid var(--purple-deep);
  }

  .orbit{position:relative; width:100%; aspect-ratio:1/1; max-width:380px; margin:0 auto;}
  .orbit-ring{position:absolute; inset:0; border-radius:50%; border:1.5px dashed rgba(108,63,160,0.35); animation:spin 34s linear infinite;}
  .orbit-ring.inner{inset:15%; border-color:rgba(59,31,82,0.3); animation-duration:24s; animation-direction:reverse;}
.orbit-core {
    position: absolute;
    inset: 32%;
    border-radius: 50%;

    background: radial-gradient(
        circle at 32% 28%,
        var(--purple-soft),
        var(--purple-deep)
    );

    display: flex;
    align-items: center;
    justify-content: center;

    color: var(--white);
    text-align: center;

    box-shadow: 0 20px 50px rgba(59, 31, 82, 0.35);

    font-size: 5rem;
    line-height: 1;
}
  /* .orbit-core strong{font-family:'Fraunces',serif; font-size:2.1rem; line-height:1;}
  .orbit-core span{font-size:0.78rem; margin-top:6px; opacity:0.85; max-width:9ch;} */
  .orbit-chip{
    position:absolute; background:var(--white); border-radius:100px; padding:8px 14px;
    font-size:0.8rem; font-weight:600; box-shadow:0 8px 20px rgba(59,31,82,0.14);
    display:flex; align-items:center; gap:6px; color:var(--purple-deep);
  }
  .orbit-chip.c1{top:2%; right:6%;}
  .orbit-chip.c2{bottom:6%; left:0%;}
  @keyframes spin{to{transform:rotate(360deg);}}
  @media (prefers-reduced-motion:reduce){ .orbit-ring{animation:none;} }

  section{scroll-margin-top:76px;}

  .services{background:var(--lilac); padding:80px 0;}
  .section-head{max-width:34ch; margin-bottom:44px;}
  .section-head .kicker{margin-bottom:10px;}
  .section-head h2{font-size:clamp(1.8rem,3vw,2.4rem); color:var(--purple-deep);}
  .service-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:1px; background:var(--line); border:1px solid var(--line); border-radius:16px; overflow:hidden;}
  .service-card{background:var(--white); padding:32px 26px;}
  .service-icon{
    width:44px; height:44px; border-radius:10px; background:var(--lilac2);
    display:flex; align-items:center; justify-content:center; font-size:1.3rem; margin-bottom:18px;
  }
  .service-card h3{font-size:1.12rem; margin-bottom:10px; color:var(--purple-deep);}
  .service-card p{font-size:0.95rem; line-height:1.6; color:#4A3A5E;}

  .about{padding:84px 0;}
  .about-top{display:grid; grid-template-columns:0.9fr 1.1fr; gap:56px; align-items:start;}
  .about-quote{
    font-family:'Fraunces',serif; font-weight:500; font-size:clamp(1.4rem,2.4vw,1.8rem);
    line-height:1.32; color:var(--purple-deep); border-left:3px solid var(--purple); padding-left:24px;
  }
  .about-body p{font-size:1.02rem; line-height:1.7; color:#4A3A5E;}
  .about-body p + p{margin-top:16px;}

  .stats{background:var(--purple-deep); color:var(--white); border-radius:20px; margin-top:56px;}
  .stats-inner{padding:38px 28px; display:grid; grid-template-columns:repeat(4,1fr); gap:24px;}
  .stat strong{display:block; font-family:'Fraunces',serif; font-size:2rem; font-weight:600;}
  .stat span{font-size:0.83rem; opacity:0.78; display:block; margin-top:4px; max-width:18ch;}

  .cta-band{
    background:linear-gradient(135deg, var(--purple-deep), var(--purple));
    color:var(--white); border-radius:22px; padding:56px 44px;
    display:flex; align-items:center; justify-content:space-between; gap:32px;
    margin:0 28px 90px; max-width:1064px; margin-left:auto; margin-right:auto;
  }
  .cta-band h2{font-size:clamp(1.6rem,2.8vw,2.1rem); max-width:16ch;}
  .cta-band p{margin-top:10px; opacity:0.85; max-width:38ch; font-size:0.98rem;}
  .cta-band .btn-primary{background:var(--white); color:var(--purple-deep); box-shadow:0 10px 24px rgba(0,0,0,0.15);}

  footer{border-top:1px solid var(--line); padding:28px; text-align:center; font-size:0.85rem; color:#6B5A7E;}

  @media (max-width:840px){
    .hero{grid-template-columns:1fr; padding-top:20px;}
    .about-top{grid-template-columns:1fr;}
    .service-grid{grid-template-columns:1fr;}
    .stats-inner{grid-template-columns:repeat(2,1fr);}
    .cta-band{flex-direction:column; align-items:flex-start; padding:38px 26px;}
  }
</style>
</head>
<body>

<nav>
  <div class="nav-inner">
    <div class="brand"><span class="brand-mark">✚</span><?= htmlspecialchars($config['brand']) ?></div>
    <div class="nav-links">
      <a href="#home">Home</a>
      <a href="#services">Services</a>
      <a href="#about">About Us</a>
    </div>
    <a class="nav-cta" href="<?= htmlspecialchars($config['book_url']) ?>">Book an appointment</a>
  </div>
</nav>

<section id="home" class="wrap hero">
  <div>
    <div class="kicker"><?= htmlspecialchars($config['kicker']) ?></div>
    <h1><?= htmlspecialchars($config['heading']) ?></h1>
    <p class="hero-sub"><?= htmlspecialchars($config['subhead']) ?></p>
    <div class="hero-actions">
      <a href="<?= htmlspecialchars($config['book_url']) ?>" class="btn-primary">Book an appointment</a>
      <a href="#about" class="btn-ghost">Our story</a>
    </div>
  </div>
  <div class="orbit">
    <div class="orbit-ring"></div>
    <div class="orbit-ring inner"></div>
 <div class="orbit-core" aria-label="Friendly dental care">
  🦷
</div>
    <?php foreach ($config['hero_chips'] as $i => $chip): ?>
      <div class="orbit-chip c<?= $i + 1 ?>"><?= htmlspecialchars($chip) ?></div>
    <?php endforeach; ?>
  </div>
</section>

<section id="services" class="services">
  <div class="wrap">
    <div class="section-head">
      <div class="kicker">Services</div>
      <h2>Care that meets you where you are</h2>
    </div>
    <div class="service-grid">
      <?php foreach ($config['services'] as $service): ?>
        <div class="service-card">
          <div class="service-icon"><?= $service['icon'] ?></div>
          <h3><?= htmlspecialchars($service['title']) ?></h3>
          <p><?= htmlspecialchars($service['body']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="about" class="wrap about">
  <div class="section-head">
    <div class="kicker">About us</div>
    <h2>Why we exist</h2>
  </div>
  <div class="about-top">
    <p class="about-quote">"<?= htmlspecialchars($config['mission_quote']) ?>"</p>
    <div class="about-body">
      <?php foreach ($config['mission_body'] as $para): ?>
        <p><?= htmlspecialchars($para) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- <div class="stats">
    <div class="stats-inner">
      <?php foreach ($config['stats'] as $stat): ?>
        <div class="stat">
          <strong><?= htmlspecialchars($stat['number']) ?></strong>
          <span><?= htmlspecialchars($stat['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div> -->
</section>

<section class="cta-band" id="book">
  <div>
    <h2><?= htmlspecialchars($config['cta_heading']) ?></h2>
    <p><?= htmlspecialchars($config['cta_body']) ?></p>
  </div>
  <a href="<?= htmlspecialchars($config['book_url']) ?>" class="btn-primary">Book an appointment</a>
</section>

<footer><?= htmlspecialchars($config['brand']) ?> Community Dental · Care for every neighborhood, every income, every smile.</footer>

</body>
</html>