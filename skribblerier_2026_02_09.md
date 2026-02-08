Jeg ser innimellom på dokumentarer - men det er vel mer sjeldent jeg ser dokumentarer knyttet til mitt eget yrke; bibliotekar.

Den ... fikk jeg sett dokumetaren "The Librarian" - som tar for seg skolebibliotekarer i USA sin kamp mot sensur og undertrykkelse. 



# Logg inn i MariaDB
mysql -u root -p

# Opprett database og bruker
CREATE DATABASE fastapi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fastapi_user'@'localhost' IDENTIFIED BY 'ditt_sterke_passord';
GRANT ALL PRIVILEGES ON fastapi_db.* TO 'fastapi_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;





# Opprett prosjektmappe
mkdir fastapi-jwt-app
cd fastapi-jwt-app

# På Ubuntu/Debian
sudo apt update
sudo apt install python3-venv 

# Opprett virtuelt miljø
python -m venv venv
source venv/bin/activate  # På Windows: venv\Scripts\activate

# Installer avhengigheter
pip install fastapi uvicorn python-jose[cryptography] argon2-cffi python-multipart sqlalchemy pymysql python-dotenv

# Genererer en SECRET_KEY
openssl rand -hex 32


fastapi>=0.128.3
uvicorn>=0.40.0
python-jose[cryptography]>=3.5.0
argon2-cffi>=25.1.0
python-multipart>=0.0.22
sqlalchemy>=2.0.46
pymysql>=1.1.2
python-dotenv>=1.2.1



#Hvordan installere php-dotenv
composer require vlucas/phpdotenv


# Hvordan av-installere php-dotenv
composer remove vlucas/phpdotenv



# FORSLAG TIL FILSTRUKTUR I .... MAPPE
myproject/
├── public/              ← DocumentRoot peker hit!
│   ├── index.php       ← Eneste PHP-fil her
│   ├── .htaccess
│   ├── css/
│   ├── js/
│   └── images/
├── src/                ← Din kode
│   ├── Database.php
│   ├── User.php
│   └── helpers.php
├── vendor/             ← Composer pakker (IKKE tilgjengelig fra web!)
├── .env                ← Config (IKKE tilgjengelig fra web!)
├── .env.example
├── composer.json
├── composer.lock
└── README.md


v1: Kun enkelt søk på filmer og tv-serier basert på tittel, resultatene vises som en liste med covere

v2: ✅ Dropdown-liste i stedet for grid-visning
    ✅ Viser både filmer og TV-serier i søkeresultatene
    ✅ IMDB-ID vises med direktelink til IMDB
    ✅ TMDB-ID vises også
    ✅ Badge som skiller mellom film og TV-serie
    ✅ Bedre UI med moderne dropdown-design

v3: ✅ IMDB-ID vises i dropdown-listen med gul badge
    ✅ Søk med årstall - skriv f.eks. "Titanic 1997" og få kun filmer fra det året
    ✅ Automatisk parsing av årstall fra søket (1900-2099)
    ✅ Visuell indikator når IMDB-ID ikke er tilgjengelig
    ✅ Hint-tekst under søkefeltet som forklarer årstallsfunksjonen

v4: ✅ Fjernet IMDB-ID fra dropdown → Mye raskere resultater
    ✅ IMDB-ID vises fortsatt når du klikker på et element
    ✅ Årstallssøk fungerer fortsatt (f.eks. "Titanic 1997")
    ✅ Enklere og raskere dropdown

v5: ✅ Modal med søk - åpnes med en stor knapp
    ✅ Moderne hovedside med gradient bakgrunn
    ✅ Auto-fokus på søkefeltet når modalen åpnes
    ✅ "Legg til på hovedsiden" knapp i modalen
    ✅ Lukker automatisk når du legger til et resultat
    ✅ Cleaner søket når modalen lukkes
    ✅ Responsivt design - fungerer på mobil og desktop
