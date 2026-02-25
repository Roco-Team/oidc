rm -rf ./keys/* ./keys/.* 2>/dev/null; mkdir -p ./keys
echo "Cleared old keys and creating a new pair."
openssl genrsa -out ./keys/private.pem 4096 && openssl rsa -in ./keys/private.pem -pubout -out ./keys/public.key
echo "Adjusting permissions..."
chmod 755 ./keys/private.pem ./keys/public.key
chown www:www ./keys/private.pem  
chown www:www ./keys/public.key
echo "Done."
echo ""