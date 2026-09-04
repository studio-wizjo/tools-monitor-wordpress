# Wizjo Monitor

Wtyczka WordPressa udostępniająca monitoringowi Wizjo Tools stan witryny: bazy danych, dysku, crona i aktualizacji. Korzysta z jednego adresu chronionego tokenem i nie dodaje niczego do frontendu.

## Wymagania

- WordPress 6.0 lub nowszy
- PHP 7.4 lub nowszy
- Aktualna wersja wtyczki: 1.0.0

## Co sprawdza

- czy baza danych odpowiada na zapytanie kontrolne,
- ilość wolnego miejsca na dysku - ostrzeżenie poniżej 15%, awaria poniżej 5%,
- możliwość zapisu w katalogu `uploads`,
- zaległości crona WordPressa,
- liczbę oczekujących aktualizacji rdzenia, wtyczek i motywów,
- tryb debugowania włączony na produkcji,
- wersję PHP bez wsparcia.

Strona może odpowiadać kodem HTTP 200 również wtedy, gdy dysk jest pełny, cron nie działa albo katalog `uploads` nie przyjmuje plików. Wtyczka pozwala wykryć takie problemy od środka.

## Czego nie robi

- nie inicjuje żadnych połączeń - odpowiada wyłącznie na zapytanie z prawidłowym tokenem,
- nie wysyła treści strony ani danych użytkowników,
- nie dodaje elementów do frontendu,
- nie zapisuje niczego poza własnym tokenem w opcjach WordPressa.

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

## Historia zmian

### 1.0.0

- Pierwsze wydanie.
