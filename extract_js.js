const fs = require('fs');
const content = fs.readFileSync('resources/views/menu-management/index.blade.php', 'utf8');
const match = content.match(/<script>(.*?)<\/script>/s);
if (match) {
    fs.writeFileSync('scratch.js', match[1]);
    console.log('Extracted scratch.js');
} else {
    console.log('No match');
}
