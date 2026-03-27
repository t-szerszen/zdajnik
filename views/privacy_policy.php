<?php

/**
 * Konfiguracja komponentu head
 */
$pageTitle = 'Polityka Prywatności | Examly';
$pageDescription = 'Przeczytaj politykę prywatności serwisu Examly. Dowiedz się, jakie dane gromadzimy i jak je chronimy.';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <header class="page-header">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient">Polityka</span> Prywatności</h1>
      <p class="page-header__text">Dokument określający zasady przetwarzania i ochrony danych osobowych użytkowników serwisu Examly.</p>
    </div>
  </header>

  <main class="legal-page">
    <div class="legal-page__container">

      <div class="legal-page__meta">
        <i class="fas fa-calendar-alt"></i>
        <span>Data wejścia w życie: <strong>26 marca 2026 r.</strong></span>
      </div>

      <article class="legal-page__content">

        <section class="legal-section">
          <h2 class="legal-section__title"><span class="legal-section__number">§ 1</span> Postanowienia ogólne</h2>
          <div class="legal-section__body">
            <p>Niniejsza Polityka Prywatności określa zasady przetwarzania i ochrony danych osobowych użytkowników korzystających z serwisu edukacyjnego <strong>Examly</strong> (dalej: „Serwis").</p>
            <p>Dokument ma charakter informacyjny i określa standardy gromadzenia, wykorzystywania oraz zabezpieczania danych, a także opisuje prawa przysługujące osobom, których dane dotyczą, zgodnie z Rozporządzeniem Parlamentu Europejskiego i Rady (UE) 2016/679 z dnia 27 kwietnia 2016 r. (RODO).</p>
            <p>Korzystanie z Serwisu wymaga przetwarzania danych niezbędnych do świadczenia usług drogą elektroniczną oraz realizacji celów edukacyjnych i statystycznych.</p>
          </div>
        </section>

        <section class="legal-section">
          <h2 class="legal-section__title"><span class="legal-section__number">§ 2</span> Zakres i cel gromadzenia danych</h2>
          <div class="legal-section__body">
            <p>Serwis przetwarza dane osobowe podane dobrowolnie przez Użytkownika w procesie rejestracji oraz dane generowane automatycznie podczas korzystania z funkcji systemu.</p>
            <p>Dane przetwarzane obejmują:</p>
            <ul class="legal-list">
              <li>
                <div class="legal-list__label">Dane identyfikacyjne</div>
                <div class="legal-list__desc">Adres poczty elektronicznej (e-mail), służący do autoryzacji dostępu oraz komunikacji systemowej.</div>
              </li>
              <li>
                <div class="legal-list__label">Dane analityczne i postępy</div>
                <div class="legal-list__desc">Historia udzielonych odpowiedzi, statystyki opanowania materiału, rejestr błędów krytycznych oraz zbiór pytań zapisanych przez Użytkownika.</div>
              </li>
              <li>
                <div class="legal-list__label">Dane techniczne</div>
                <div class="legal-list__desc">Adres IP, informacje o urządzeniu końcowym, typ przeglądarki oraz logi serwerowe gromadzone w celu zapewnienia bezpieczeństwa teleinformatycznego.</div>
              </li>
            </ul>
            <p>Cele przetwarzania danych obejmują:</p>
            <ul class="legal-list legal-list--simple">
              <li>Świadczenie usług drogą elektroniczną w zakresie udostępniania materiałów dydaktycznych i narzędzi do weryfikacji wiedzy.</li>
              <li>Personalizację procesu nauczania poprzez algorytmy wyliczające skuteczność opanowania materiału.</li>
              <li>Zapewnienie bezpieczeństwa Serwisu oraz ochronę przed nadużyciami.</li>
            </ul>
          </div>
        </section>

        <section class="legal-section">
          <h2 class="legal-section__title"><span class="legal-section__number">§ 3</span> Mechanizmy przechowywania informacji (Pliki Cookies)</h2>
          <div class="legal-section__body">
            <p>Serwis wykorzystuje pliki typu <strong>cookies</strong> oraz pokrewne technologie w celu zapewnienia prawidłowego działania warstwy technicznej aplikacji.</p>
            <p>Wykorzystywane pliki cookies dzielą się na:</p>
            <ul class="legal-list">
              <li>
                <div class="legal-list__label">Pliki sesyjne</div>
                <div class="legal-list__desc">Niezbędne do utrzymania stanu zalogowania Użytkownika podczas nawigacji pomiędzy podstronami Serwisu.</div>
              </li>
              <li>
                <div class="legal-list__label">Pliki bezpieczeństwa</div>
                <div class="legal-list__desc">Służące do generowania i weryfikacji tokenów zabezpieczających przed atakami typu Cross-Site Request Forgery (CSRF).</div>
              </li>
              <li>
                <div class="legal-list__label">Pliki funkcjonalne</div>
                <div class="legal-list__desc">Umożliwiające zapamiętanie preferencji interfejsu, takich jak wybrany schemat kolorystyczny (tryb jasny/ciemny).</div>
              </li>
            </ul>
            <div class="legal-note">
              <i class="fas fa-info-circle"></i>
              <span>Użytkownik ma możliwość ograniczenia lub wyłączenia obsługi plików cookies w ustawieniach przeglądarki, jednak może to skutkować nieprawidłowym działaniem kluczowych funkcji Serwisu.</span>
            </div>
          </div>
        </section>

        <section class="legal-section">
          <h2 class="legal-section__title"><span class="legal-section__number">§ 4</span> Procedura usuwania konta i retencja danych</h2>
          <div class="legal-section__body">
            <p>Użytkownikowi przysługuje prawo do usunięcia konta w dowolnym momencie za pośrednictwem ustawień profilu.</p>
            <p>Z chwilą zgłoszenia żądania usunięcia konta, Serwis niezwłocznie uruchamia procedurę anonimizacji danych dotyczących postępów w nauce. Statystyki odpowiedzi zostają trwale odłączone od identyfikatorów osobowych Użytkownika i są przechowywane wyłącznie jako dane zagregowane.</p>
            <div class="legal-highlight">
              <i class="fas fa-clock"></i>
              <div>
                <strong>Okres retencji: 30 dni</strong>
                <p>Serwis stosuje trzydziestodniowy okres ograniczonego dostępu przed ostatecznym usunięciem danych. W tym czasie adres e-mail pozostaje w bazie w stanie uśpienia, co umożliwia przywrócenie konta na wyraźne żądanie.</p>
              </div>
            </div>
            <p>Po upływie trzydziestu dni od daty zgłoszenia żądania, adres e-mail oraz wszelkie dane pozwalające na identyfikację osoby zostają <strong>trwale i bezpowrotnie usunięte</strong> z systemów produkcyjnych oraz kopii zapasowych Serwisu.</p>
          </div>
        </section>

        <section class="legal-section">
          <h2 class="legal-section__title"><span class="legal-section__number">§ 5</span> Prawa Użytkownika</h2>
          <div class="legal-section__body">
            <p>Każdej osobie, której dane dotyczą, przysługuje prawo do:</p>
            <ul class="legal-list legal-list--rights">
              <li><i class="fas fa-eye"></i> <span>Dostępu do treści swoich danych oraz otrzymania ich kopii w formacie ustrukturyzowanym.</span></li>
              <li><i class="fas fa-pen"></i> <span>Sprostowania danych, jeżeli są one nieprawidłowe lub niekompletne.</span></li>
              <li><i class="fas fa-trash-alt"></i> <span>Żądania usunięcia danych (prawo do bycia zapomnianym) z uwzględnieniem procedury opisanej w § 4.</span></li>
              <li><i class="fas fa-pause-circle"></i> <span>Ograniczenia przetwarzania danych oraz prawo do przenoszenia danych.</span></li>
              <li><i class="fas fa-hand-paper"></i> <span>Wniesienia sprzeciwu wobec przetwarzania danych opartego na prawnie uzasadnionym interesie Administratora.</span></li>
            </ul>
            <p>W celu realizacji powyższych praw Użytkownik może skontaktować się z Administratorem drogą elektroniczną.</p>
          </div>
        </section>

        <section class="legal-section">
          <h2 class="legal-section__title"><span class="legal-section__number">§ 6</span> Bezpieczeństwo danych</h2>
          <div class="legal-section__body">
            <p>Serwis stosuje zaawansowane środki techniczne i organizacyjne w celu zapewnienia poufności i integralności przetwarzanych informacji.</p>
            <div class="legal-security-grid">
              <div class="legal-security-item">
                <i class="fas fa-lock"></i>
                <div>
                  <strong>Szyfrowanie SSL/TLS</strong>
                  <p>Komunikacja pomiędzy urządzeniem Użytkownika a serwerem jest szyfrowana przy użyciu protokołu SSL/TLS.</p>
                </div>
              </div>
              <div class="legal-security-item">
                <i class="fas fa-key"></i>
                <div>
                  <strong>Bezpieczne hasła</strong>
                  <p>Hasła przechowywane są w formie niejawnej z użyciem bezpiecznych, jednostronnych funkcji skrótu o wysokiej odporności na próby złamania.</p>
                </div>
              </div>
            </div>
          </div>
        </section>

      </article>

    </div>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>
</html>
