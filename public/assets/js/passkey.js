const Passkey = {
    // Utils
    bufferToBase64url: function(buffer) {
        const bytes = new Uint8Array(buffer);
        let str = '';
        for (const char of bytes) {
            str += String.fromCharCode(char);
        }
        return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    },
    base64urlToBuffer: function(base64url) {
        const padding = '='.repeat((4 - base64url.length % 4) % 4);
        const base64 = (base64url + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray.buffer;
    },

    register: async function() {
        try {
            // 1. Get options
            const res = await fetch('/account/passkey/register/options');
            if (!res.ok) throw new Error('Failed to fetch options');
            const options = await res.json();
            
            options.challenge = this.base64urlToBuffer(options.challenge);
            options.user.id = this.base64urlToBuffer(options.user.id);
            if (options.excludeCredentials) {
                for (let cred of options.excludeCredentials) {
                    cred.id = this.base64urlToBuffer(cred.id);
                }
            }

            // 2. Create credential
            const cred = await navigator.credentials.create({ publicKey: options });
            
            // 3. Send to server
            const credential = {
                id: cred.id,
                rawId: this.bufferToBase64url(cred.rawId),
                type: cred.type,
                response: {
                    clientDataJSON: this.bufferToBase64url(cred.response.clientDataJSON),
                    attestationObject: this.bufferToBase64url(cred.response.attestationObject)
                }
            };

            const verifyRes = await fetch('/account/passkey/register/verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(credential)
            });
            
            const result = await verifyRes.json();
            if (result.success) {
                alert('Passkey registered successfully!');
                location.reload();
            } else {
                alert('Registration failed: ' + (result.message || 'Unknown error'));
            }
        } catch (e) {
            console.error(e);
            alert('Error: ' + e.message);
        }
    },

    login: async function() {
        try {
            const res = await fetch('/login/passkey/options');
            if (!res.ok) throw new Error('Failed to fetch options');
            const options = await res.json();
            
            options.challenge = this.base64urlToBuffer(options.challenge);
            if (options.allowCredentials) {
                for (let cred of options.allowCredentials) {
                    cred.id = this.base64urlToBuffer(cred.id);
                }
            }

            const cred = await navigator.credentials.get({ publicKey: options });
            
            const assertion = {
                id: cred.id,
                rawId: this.bufferToBase64url(cred.rawId),
                type: cred.type,
                response: {
                    clientDataJSON: this.bufferToBase64url(cred.response.clientDataJSON),
                    authenticatorData: this.bufferToBase64url(cred.response.authenticatorData),
                    signature: this.bufferToBase64url(cred.response.signature),
                    userHandle: cred.response.userHandle ? this.bufferToBase64url(cred.response.userHandle) : null
                }
            };

            const verifyRes = await fetch('/login/passkey/verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(assertion)
            });
            
            const result = await verifyRes.json();
            if (result.success) {
                if (result.redirect) location.href = result.redirect;
                else location.href = '/account';
            } else {
                document.getElementById('message').innerText = 'Login failed: ' + (result.message || 'Unknown error');
            }
        } catch (e) {
            console.error(e);
            document.getElementById('message').innerText = 'Login failed: ' + e.message;
        }
    }
};
