FROM stilliard/pure-ftpd

# Vytvoření symlinku, aby FTP kontejner četl databázi ze sdíleného svazku
RUN rm -f /etc/pure-ftpd/pureftpd.pdb \
    && ln -s /etc/pure-ftpd/passwd/pureftpd.pdb /etc/pure-ftpd/pureftpd.pdb