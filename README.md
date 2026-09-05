# Wizjo Monitor

Wtyczka WordPressa udostępniająca monitoringowi Wizjo Tools stan witryny: bazy danych, dysku, crona i aktualizacji. Korzysta z jednego adresu chronionego tokenem i nie dodaje niczego do frontendu.

## Wymagania

- WordPress 6.0 lub nowszy
- PHP 7.4 lub nowszy
- Aktualna wersja wtyczki: 1.1.1

## Co sprawdza

- czy baza danych odpowiada na zapytanie kontrolne,
- ilość wolnego miejsca na dysku wraz z liczbą wolnych GB,
- rzeczywistą możliwość zapisu, odczytu i usunięcia pliku w katalogu `uploads`,
- zaległości crona WordPressa wraz z nazwą najstarszego hooka,
- liczbę oczekujących aktualizacji rdzenia, wtyczek i motywów,
- tryb debugowania włączony na produkcji,
- wersję PHP bez wsparcia.

Strona może odpowiadać kodem HTTP 200 również wtedy, gdy dysk jest pełny, cron nie działa albo katalog `uploads` nie przyjmuje plików. Wtyczka pozwala wykryć takie problemy od środka.

## Czego nie robi

- nie inicjuje żadnych połączeń - odpowiada wyłącznie na zapytanie z prawidłowym tokenem,
- nie wysyła treści strony ani danych użytkowników,
- nie dodaje elementów do frontendu,
- nie pozostawia plików ani danych poza własnym tokenem w opcjach WordPressa;
  plik użyty do testu zapisu jest od razu usuwany.

## Instalacja

1. W panelu WordPressa wybierz **Wtyczki > Dodaj wtyczkę > Wyślij wtyczkę na serwer**.
2. Wgraj paczkę ZIP i włącz wtyczkę.
3. Przejdź do **Ustawienia > Wizjo Monitor**.
4. Wklej token wygenerowany w panelu Wizjo Tools.
5. Skopiuj pokazany adres i wpisz go w ustawieniach kontroli w panelu monitoringu.

## Najczęstsze pytania

### Czy adres diagnostyczny jest publiczny?

Adres jest publiczny, ale bez poprawnego tokenu odpowiada kodem 403. Gdy token nie został jeszcze skonfigurowany, zwraca kod 503, aby można było odróżnić brak konfiguracji od awarii.

### Co zrobić po zmianie tokenu w panelu?

Wpisz ten sam token w **Ustawienia > Wizjo Monitor**. Do czasu aktualizacji tokenu monitoring będzie zgłaszał, że wtyczka odrzuca uwierzytelnienie.

## Aktualizacje

Docelowym kanałem aktualizacji jest oficjalny katalog WordPress.org. Repozytorium
GitHub pozostaje miejscem rozwoju, a wydania są synchronizowane do repozytorium
WordPress.org po oznaczeniu wersji tagiem.

## Historia zmian

### 1.1.1

- osobny adres strony wtyczki wymagany przez katalog WordPress.org.

### 1.1.0

- poprawione wykrywanie wolnego miejsca na hostingach współdzielonych,
- rzeczywisty test zapisu w katalogu uploads,
- nazwa zaległego zadania WP-Cron w odpowiedzi diagnostycznej,
- token przyjmowany wyłącznie w nagłówku `X-Wizjo-Token`.

### 1.0.0

- Pierwsze wydanie.
