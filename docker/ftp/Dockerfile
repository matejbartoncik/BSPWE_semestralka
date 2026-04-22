FROM stilliard/pure-ftpd

RUN rm -f /etc/pure-ftpd/pureftpd.pdb \
    && ln -s /etc/pure-ftpd/passwd/pureftpd.pdb /etc/pure-ftpd/pureftpd.pdb

COPY docker/ftp/start.sh /start.sh
RUN chmod +x /start.sh