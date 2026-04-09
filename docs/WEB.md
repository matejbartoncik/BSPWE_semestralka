# WEB - Apache path hosting

## Cíl

Apache musí zachovat:

- `http://localhost/` → admin app (`./app`)
- `http://localhost/customer.cz/` → `data/www/customer/public/`
- vše se mapuje na port 80 (standartní port pro prohlížeče)
Obecně:

- `http://localhost/~{name}/` → `data/www/{name}/public/`

## Implementace

V Docker Compose jsou použity mounty:

- `./app:/var/www/html:rw`
- `./data/www:/srv/www:rw`
- `./docker/apache/000-default.conf:/etc/apache2/sites-enabled/000-default.conf:ro`

Admin app zůstává na `/` přes:

```apache
DocumentRoot /var/www/html
