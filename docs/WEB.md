# WEB - Apache path hosting

## Cíl

Apache musí zachovat:

- `http://localhost:8080/` → admin app (`./app`)
- `http://localhost:8080/~customer/` → `data/www/customer/public/`

Obecně:

- `http://localhost:8080/~{name}/` → `data/www/{name}/public/`

## Implementace

V Docker Compose jsou použity mounty:

- `./app:/var/www/html:rw`
- `./data/www:/srv/www:rw`
- `./docker/apache/000-default.conf:/etc/apache2/sites-enabled/000-default.conf:ro`

Admin app zůstává na `/` přes:

```apache
DocumentRoot /var/www/html
