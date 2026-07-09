
const BASE_URL = 'https://saddlebrown-badger-327057.hostingersite.com/backend';

async function run() {
    const email = `test_${Date.now()}@example.com`;
    const password = 'password123';
    const name = 'Test User';
    let cookie = '';

    console.log(`Attempting to register with email: ${email}`);

    try {
        // 1. Register
        console.log('1. Registering...');
        const regResponse = await fetch(`${BASE_URL}/auth.php?action=register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const regData = await regResponse.json();
        console.log('Registration Response:', regResponse.status, regData);

        // Capture cookie
        const setCookie = regResponse.headers.get('set-cookie');
        if (setCookie) {
            cookie = setCookie.split(';')[0];
            console.log('Cookie from register:', cookie);
        }

        // 2. Login
        console.log('2. Logging in...');
        const loginResponse = await fetch(`${BASE_URL}/auth.php?action=login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Cookie': cookie
            },
            body: JSON.stringify({ email, password })
        });

        const loginData = await loginResponse.json();
        console.log('Login Response:', loginResponse.status, loginData);

        const loginCookie = loginResponse.headers.get('set-cookie');
        if (loginCookie) {
            cookie = loginCookie.split(';')[0];
            console.log('Cookie from login:', cookie);
        }

        if (!cookie) {
            console.warn('No session cookie received! Authentication might fail if session is not cookie-based or strict.');
        }

        // 3. Update Profile
        console.log('3. Updating Profile with just name...');
        try {
            const updateResponse = await fetch(`${BASE_URL}/profile.php?action=update_profile`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Cookie': cookie
                },
                body: JSON.stringify({ name: name })
                // missing phone, address, city
            });

            // Try to parse JSON
            const updateText = await updateResponse.text();
            try {
                const updateData = JSON.parse(updateText);
                console.log('Update Profile Response:', updateResponse.status, updateData);
            } catch (e) {
                console.error('Update Profile Failed (Non-JSON response):', updateResponse.status);
                // console.error('Response Body:', updateText); // Do not enable this, may contain large HTML
            }

        } catch (err) {
            console.error('Update Profile Request Error:', err);
        }

    } catch (error) {
        console.error('An error occurred during the flow:', error);
    }
}

run();
