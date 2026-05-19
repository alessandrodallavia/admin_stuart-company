<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<!-- Titolo e SEO -->
<title>@yield('meta_title', 'Stuart Company')</title>
<meta name="description" content="@yield('meta_description', 'Crea lo shop ufficiale della tua scuola: merchandising scolastico Made in Italy, felpe e maglioni esclusivi, qualità premium senza minimi d\'ordine.')">

<meta name="csrf-token" content="{{ csrf_token() }}">

<!-- 🧩 PRELOAD: immagine LCP principale (hero) -->
<link rel="preload" as="image" href="{{ asset('images/assets/hero-merchandising-scolastico-stuart-1024.webp') }}" fetchpriority="high" importance="high">

<!-- Open Graph -->
<meta property="og:title" content="@yield('meta_title', 'Merchandising scolastico personalizzato Made in Italy - Stuart')">
<meta property="og:description" content="@yield('meta_description', 'Crea lo shop ufficiale della tua scuola: merchandising scolastico Made in Italy, felpe e maglioni esclusivi, qualità premium senza minimi d\'ordine.')">
<meta property="og:image" content="@yield('meta_image', asset('/images/logos/logo-stuart-og-twitter.jpg'))">
<meta property="og:type" content="@yield('meta_type', 'website')">
<meta property="og:url" content="{{ url()->current() }}">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="@yield('meta_title', 'Merchandising scolastico personalizzato Made in Italy - Stuart')">
<meta name="twitter:description" content="@yield('meta_description', 'Crea lo shop ufficiale della tua scuola: merchandising scolastico Made in Italy, felpe e maglioni esclusivi, qualità premium senza minimi d\'ordine.')">
<meta name="twitter:image" content="@yield('meta_image', asset('/images/logos/logo-stuart-og-twitter.jpg'))">

<!-- Icone -->
<link rel="icon" type="image/png" href="{{ asset('/assets/logos/logo-bullstar-stella.png') }}" />
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('/assets/logos/logo-bullstar-stella-apple-touch-icon.png') }}">

<!-- 🧩 PRECONNECT: riduce la latenza iniziale (DNS + TLS handshake) -->
<link rel="preconnect" href="https://bullstar.it" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- Google Fonts (non bloccante) -->
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Roboto:ital,wght@0,100..900&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Roboto:ital,wght@0,100..900&display=swap" media="print" onload="this.media='all'">
<noscript>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Roboto:ital,wght@0,100..900&display=swap">
</noscript>

<!-- jQuery (differito, non blocca rendering) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>

<!-- CSS/JS -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>[x-cloak]{ display:none !important }</style>

@livewireStyles
@stack('styles')

<!-- Google Maps + reCAPTCHA caricati solo con consenso marketing -->
<script type="text/plain" data-consent="marketing" src="https://unpkg.com/@googlemaps/extended-component-library@0.6" defer></script>
<script type="text/plain" data-consent="marketing" src="https://www.google.com/recaptcha/api.js?render=SITE_KEY" async defer></script>

@if(app()->environment('local'))

    <script src="https://t.contentsquare.net/uxa/4088053069168.js"></script>

    <link rel="preconnect" href="https://www.googletagmanager.com">
    <!-- GTAG + Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZMHBX4W5QX"></script>
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-763274553"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        window.dataLayer.push({
            'page_type': 'landing'
        });

        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-ZMHBX4W5QX');
        gtag('config', 'AW-763274553');
    </script>

    <!-- Google Tag Manager -->
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-TSP4RQ2');
    </script>
    <!-- End Google Tag Manager -->

  @verbatim
      <!-- Schema JSON-LD -->
      <script type="application/ld+json">
          {
            "@context": "https://schema.org",
            "@type": "WebSite",
            "url": "https://bullstar.it/",
            "name": "Bullstar",
            "alternateName": "Bullstar"
          }
      </script>

      <script type="application/ld+json">
          {
            "@context": "https://schema.org",
            "@type": "Organization",
            "name": "Bullstar",
            "alternateName": "Stuart",
            "url": "https://bullstar.it/",
            "logo": "https://bullstar.it/assets/logos/logo-bullstar-stella.png"
          }
      </script>
  @endverbatim

    <!-- Tracciamento click WhatsApp -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[button-cta]').forEach(el => {
                el.addEventListener('click', async function () {
                    let uuid = sessionStorage.getItem('lead_uuid');

                    const win = window.open('about:blank', '_blank');

                    try {

                        const response = await fetch('/lead-click', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                uuid,
                                page: window.location.href,
                                cta: this.dataset.eventArea || 'unknown'
                            })
                        });

                        const data = await response.json();

                        uuid = data.uuid;
                        sessionStorage.setItem('lead_uuid', uuid);

                        const message = [
                            "Ciao, vorrei vedere un mockup per delle t-shirt personalizzate.",
                            "",
                            `ID richiesta: ${uuid}`
                        ].join("\n");

                        const text = encodeURIComponent(message);

                        win.location.href = `https://wa.me/{{ config('services.whatsapp.phone_api') }}?text=${text}`;

                    } catch (error) {
                        console.error(error);
                        win.location.href = `https://wa.me/{{ config('services.whatsapp.phone_api') }}`;
                    }

                    if (typeof gtag === 'function') {
                        gtag('event', 'contact_whatsapp_click', {
                            page_name: el.dataset.eventPage || 'unknown',
                            page_area: el.dataset.eventArea || 'unknown',
                            cta_name: el.dataset.eventCta || 'whatsapp_cta',
                            cta_text: el.textContent.trim() || 'Pulsante WhatsApp'
                        });
                    }
                });
            });

            document.querySelectorAll('[button-call]').forEach(el => {
                el.addEventListener('click', async function () {
                    if (typeof gtag === 'function') {
                        gtag('event', 'contact_call_click', {
                            page_name: el.dataset.eventPage || 'unknown',
                            page_area: el.dataset.eventArea || 'unknown',
                            cta_name: el.dataset.eventCta || 'call_cta',
                            cta_text: el.textContent.trim() || 'Pulsante Call'
                        });
                    }
                });
            });
        });
    </script>


    <!-- Meta Pixel -->
    <script>
      !function(f,b,e,v,n,t,s)
      {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
      n.queue=[];t=b.createElement(e);t.async=!0;
      t.src=v;s=b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t,s)}(window, document,'script',
      'https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', '356478581578783');
      fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=356478581578783&ev=PageView&noscript=1"
    /></noscript>
    <!-- End Meta Pixel Code -->
@endif
