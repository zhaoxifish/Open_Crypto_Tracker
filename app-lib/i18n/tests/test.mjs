// Browser regression runner. Node 22+ and a local Chrome installation; no packages.
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import http from 'node:http';
import {spawn} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {setTimeout as delay} from 'node:timers/promises';
const tests = path.dirname(fileURLToPath(import.meta.url));
const root = path.dirname(tests);
const candidates = [process.env.CHROME_PATH, process.env.ProgramFiles && path.join(process.env.ProgramFiles, 'Google/Chrome/Application/chrome.exe'), process.env['ProgramFiles(x86)'] && path.join(process.env['ProgramFiles(x86)'], 'Google/Chrome/Application/chrome.exe'), '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/usr/bin/google-chrome', '/usr/bin/chromium'];
const chrome = candidates.find(candidate => candidate && fs.existsSync(candidate));
if (!chrome) throw new Error('Chrome not found. Set CHROME_PATH to your browser executable.');
const files = {'/tests/regression.html':path.join(tests,'regression.html'), '/i18n.js':path.join(root,'i18n.js'), '/zh-CN.js':path.join(root,'zh-CN.js'), '/zh-CN.css':path.join(root,'zh-CN.css')};
const server = http.createServer((request,response) => {
    const file=files[request.url.split('?')[0]];
    if (!file) {response.writeHead(404);response.end();return;}
    response.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript; charset=utf-8' : file.endsWith('.css') ? 'text/css; charset=utf-8' : 'text/html; charset=utf-8');
    response.setHeader('Cache-Control','no-store');response.end(fs.readFileSync(file));
});
await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
const tempRoot=path.resolve(os.tmpdir());
const profile=fs.mkdtempSync(path.join(tempRoot,'oct-i18n-regression-'));
const browser=spawn(chrome,['--headless=new','--no-first-run','--no-default-browser-check','--disable-gpu','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
let socket;
try {
    const portFile=path.join(profile,'DevToolsActivePort');
    for(let i=0;i<100&&!fs.existsSync(portFile);i++)await delay(100);
    if(!fs.existsSync(portFile))throw new Error('Browser debugging endpoint did not start.');
    const port=fs.readFileSync(portFile,'utf8').split('\n')[0];
    const tabs=await (await fetch('http://127.0.0.1:'+port+'/json/list')).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
    const pending=new Map();let id=0;
    socket.addEventListener('message',event=>{const item=JSON.parse(event.data);if(item.id&&pending.has(item.id)){const p=pending.get(item.id);pending.delete(item.id);item.error?p.reject(item.error):p.resolve(item.result);}});
    const call=(method,params={})=>new Promise((resolve,reject)=>{const requestId=++id;pending.set(requestId,{resolve,reject});socket.send(JSON.stringify({id:requestId,method,params}));});
    await call('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/tests/regression.html'});
    let result;
    for(let i=0;i<100;i++){await delay(100);const value=await call('Runtime.evaluate',{expression:'window.REGRESSION_RESULT||null',returnByValue:true});if(value.result.value){result=value.result.value;break;}}
    if(!result)throw new Error('Browser regression timed out.');
    if(result.failed) {console.error(JSON.stringify(result.results.filter(test=>!test.passed),null,2));process.exitCode=1;}
    console.log(`Browser regression: ${result.passed} passed, ${result.failed} failed.`);
    await call('Browser.close');
} finally {
    socket?.close();browser.kill();
    server.closeAllConnections();await new Promise(resolve=>server.close(resolve));
    await delay(400);
    // Only remove this runner's unique temporary browser profile.
    const resolved=path.resolve(profile);
    if(path.dirname(resolved)===tempRoot&&path.basename(resolved).startsWith('oct-i18n-regression-')) {
        try {fs.rmSync(resolved,{recursive:true,force:true,maxRetries:5,retryDelay:200});} catch {console.warn('Temporary profile retained: '+resolved);}
    }
}
