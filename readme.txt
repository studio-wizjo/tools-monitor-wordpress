=== Wizjo Monitor ===
Contributors: wizjo
Tags: monitoring, health, uptime, diagnostyka
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Udostepnia monitoringowi Wizjo Tools stan tej witryny: baza, dysk, cron,
aktualizacje. Jeden adres chroniony tokenem i nic poza tym.

== Description ==

Wtyczka pokazuje to, czego z zewnatrz nie widac. Strona odpowiada kodem 200
takze wtedy, gdy dysk jest pelny, cron nie ruszyl od wczoraj, a katalog
uploads przestal przyjmowac pliki - i wlasnie o tych rzeczach dowiadujesz sie
zwykle od klienta, a nie od monitoringu.

Co sprawdza:

* baza danych odpowiada na zapytanie kontrolne,
* wolne miejsce na dysku (ostrzezenie ponizej 15%, awaria ponizej 5%),
* katalog uploads przyjmuje zapis,
* zaleglosci crona WordPressa,
* liczba oczekujacych aktualizacji rdzenia, wtyczek i motywow,
* tryb debugowania wlaczony na produkcji,
* wersja PHP bez wsparcia.

Czego nie robi:

* nie dzwoni nigdzie sama - odpowiada wylacznie na zapytanie z tokenem,
* nie wysyla tresci strony ani danych uzytkownikow,
* nie dodaje niczego do frontendu,
* nie zapisuje niczego poza wlasnym tokenem w opcjach.

== Installation ==

1. Wgraj wtyczke przez Wtyczki - Dodaj wtyczke - Wyslij wtyczke na serwer.
2. Wlacz ja.
3. Wejdz w Ustawienia - Wizjo Monitor i wklej token z panelu Wizjo Tools.
4. Skopiuj pokazany adres i wpisz go przy kontroli w panelu.

== Frequently Asked Questions ==

= Czy adres jest publiczny? =

Adres jest publiczny, ale bez poprawnego tokenu odpowiada kodem 403. Bez
wpisanego tokenu odpowiada 503, zeby dalo sie odroznic "nieskonfigurowane"
od "nie dziala".

= Co zrobic po zmianie tokenu w panelu? =

Wpisac ten sam token w Ustawienia - Wizjo Monitor. Do tego czasu monitoring
bedzie zglaszal, ze wtyczka nie przyjmuje tokenu.

== Changelog ==

= 1.0.0 =
* Pierwsza wersja.
