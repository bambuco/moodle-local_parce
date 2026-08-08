// This file is part of Moodle - http://moodle.org/

/** Two-column persistent history browser. */

import Ajax from 'core/ajax';
import Log from 'core/log';
import {getString, getStrings} from 'core/str';
import Templates from 'core/templates';

let config;
let strings;
let activeQuery = '';
let conversationRequest = 0;
let navigationRequest = 0;
const contextRequests = new WeakMap();

const root = () => document.getElementById('local-parce-history');
const region = (name) => root().querySelector(`[data-region="${name}"]`);

const formatDate = (timestamp) => new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
}).format(new Date(timestamp * 1000));

const render = async(context) => {
    const result = await Templates.renderForPromise('local_parce/history_items', context);
    return result;
};

const replace = async(node, context, current = () => true) => {
    const {html, js} = await render(context);
    if (!current()) {
        return false;
    }
    Templates.replaceNodeContents(node, html, js);
    return true;
};

const showListStatus = (message = '') => {
    const status = region('list-status');
    status.textContent = message;
    status.hidden = message === '';
};

const showLimit = async(node, limited, limit, current = () => true) => {
    node.hidden = true;
    node.textContent = '';
    if (limited) {
        const message = await getString('historyresultslimited', 'local_parce', limit);
        if (!current()) {
            return;
        }
        node.textContent = message;
        node.hidden = false;
    }
};

const conversationData = (conversation, chatid) => ({
    ...conversation,
    chatid,
    title: formatDate(conversation.lastactivity),
    turnslabel: strings.turns,
    promptlabel: strings.prompttokens,
    completionlabel: strings.completiontokens,
    displayname: config.mode === 'admin' ? conversation.displayname : '',
});

const contextData = (context, expanded = false) => ({
    ...context,
    expanded,
    conversations: (context.conversations || []).map((item) => conversationData(item, context.chatid)),
});

const selectInitialConversation = () => {
    if (!config.conversationkey) {
        return;
    }
    const button = root().querySelector(`[data-conversationkey="${CSS.escape(config.conversationkey)}"]`);
    if (button) {
        button.click();
        config.conversationkey = '';
    }
};

const loadConversations = async(contextNode) => {
    if (contextNode.dataset.loaded === 'true') {
        selectInitialConversation();
        return;
    }
    if (contextRequests.has(contextNode)) {
        await contextRequests.get(contextNode);
        return;
    }
    const request = loadConversationsRequest(contextNode);
    contextRequests.set(contextNode, request);
    try {
        await request;
    } finally {
        contextRequests.delete(contextNode);
    }
};

const loadConversationsRequest = async(contextNode) => {
    const status = contextNode.querySelector('[data-region="context-status"]');
    status.textContent = strings.loading;
    status.hidden = false;
    try {
        const response = await Ajax.call([{
            methodname: 'local_parce_list_history_conversations',
            args: {
                chatid: Number(contextNode.dataset.chatid),
                userid: config.mode === 'admin' ? Number(config.userid) : 0,
                mode: config.mode,
                cursor: '',
                limit: 0,
            },
        }])[0];
        await replace(contextNode.querySelector('[data-region="conversation-list"]'), {
            conversations: response.conversations.map((item) => conversationData(item, Number(contextNode.dataset.chatid))),
        });
        contextNode.dataset.loaded = 'true';
        status.textContent = response.conversations.length ? '' : strings.empty;
        status.hidden = response.conversations.length > 0;
        await showLimit(contextNode.querySelector('[data-region="context-limit"]'),
            response.limited, response.resultlimit);
        if (config.conversationkey && !root().querySelector(
            `[data-conversationkey="${CSS.escape(config.conversationkey)}"]`)) {
            const exact = await Ajax.call([{
                methodname: 'local_parce_list_history_conversations',
                args: {chatid: Number(contextNode.dataset.chatid), userid: config.mode === 'admin' ? Number(config.userid) : 0,
                    mode: config.mode, cursor: '', limit: 1, conversationkey: config.conversationkey},
            }])[0];
            if (exact.conversations.length) {
                const {html, js} = await render({conversations: exact.conversations.map((item) =>
                    conversationData(item, Number(contextNode.dataset.chatid)))});
                Templates.appendNodeContents(contextNode.querySelector('[data-region="conversation-list"]'), html, js);
            }
        }
        selectInitialConversation();
    } catch (error) {
        status.textContent = strings.error;
        status.hidden = false;
        Log.debug('Parce: conversation list load failed', error);
    }
};

const resetDetail = () => {
    conversationRequest++;
    region('conversation-header').hidden = true;
    region('conversation-placeholder').hidden = false;
    region('conversation-status').hidden = true;
    region('turns').replaceChildren();
};

const expandContext = async(contextNode) => {
    const button = contextNode.querySelector('[data-action="toggle-context"]');
    const content = contextNode.querySelector('[data-region="context-conversations"]');
    button.setAttribute('aria-expanded', 'true');
    content.hidden = false;
    await loadConversations(contextNode);
};

const loadContexts = async() => {
    const request = ++navigationRequest;
    resetDetail();
    showListStatus(strings.loading);
    await showLimit(region('list-limit'), false, 0);
    try {
        let contexts;
        let limitdata = null;
        if (config.mode === 'admin') {
            contexts = [{chatid: config.chatid, name: config.contextname}];
        } else {
            const response = await Ajax.call([{
                methodname: 'local_parce_list_history_contexts',
                args: {cursor: '', limit: 0},
            }])[0];
            contexts = response.contexts;
            limitdata = response;
        }
        const replaced = await replace(region('contexts'), {contexts: contexts.map((item) => contextData(item))},
            () => request === navigationRequest);
        if (!replaced) {
            return;
        }
        showListStatus(contexts.length ? '' : strings.empty);
        if (limitdata) {
            await showLimit(region('list-limit'), limitdata.limited, limitdata.resultlimit,
                () => request === navigationRequest);
        }
        if (config.chatid) {
            const contextNode = region('contexts').querySelector(`[data-chatid="${Number(config.chatid)}"]`);
            if (contextNode) {
                await expandContext(contextNode);
            }
        }
    } catch (error) {
        if (request === navigationRequest) {
            showListStatus(strings.error);
        }
        Log.debug('Parce: context list load failed', error);
    }
};

const highlight = (container, phrase) => {
    if (!phrase) {
        return;
    }
    const escaped = phrase.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const expression = new RegExp(escaped, 'giu');
    const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) {
        const parent = walker.currentNode.parentElement;
        if (walker.currentNode.nodeValue && expression.test(walker.currentNode.nodeValue) &&
                !parent.closest('script, style, mark')) {
            nodes.push(walker.currentNode);
        }
        expression.lastIndex = 0;
    }
    nodes.forEach((node) => {
        const fragment = document.createDocumentFragment();
        let last = 0;
        node.nodeValue.replace(expression, (match, offset) => {
            fragment.append(document.createTextNode(node.nodeValue.slice(last, offset)));
            const mark = document.createElement('mark');
            mark.textContent = match;
            fragment.append(mark);
            last = offset + match.length;
            return match;
        });
        fragment.append(document.createTextNode(node.nodeValue.slice(last)));
        node.replaceWith(fragment);
        expression.lastIndex = 0;
    });
};

const selectConversation = async(button) => {
    const request = ++conversationRequest;
    const query = activeQuery;
    root().querySelectorAll('[data-action="select-conversation"].active').forEach((item) => item.classList.remove('active'));
    button.classList.add('active');
    region('conversation-placeholder').hidden = true;
    region('conversation-header').hidden = false;
    region('conversation-title').textContent = button.dataset.title;
    region('turns').replaceChildren();
    const status = region('conversation-status');
    status.textContent = strings.loading;
    status.hidden = false;
    try {
        const response = await Ajax.call([{
            methodname: 'local_parce_get_history_turns',
            args: {
                chatid: Number(button.dataset.chatid),
                conversationkey: button.dataset.conversationkey,
                userid: Number(button.dataset.userid),
                cursor: '',
                limit: 100,
            },
        }])[0];
        if (request !== conversationRequest) {
            return;
        }
        const turns = response.turns.map((turn) => ({...turn, time: formatDate(turn.timecreated)}));
        const replaced = await replace(region('turns'), {turns}, () => request === conversationRequest);
        if (!replaced) {
            return;
        }
        status.textContent = turns.length ? '' : strings.empty;
        status.hidden = turns.length > 0;
        highlight(region('turns'), query);
        region('conversation').scrollTop = 0;
    } catch (error) {
        if (request === conversationRequest) {
            status.textContent = strings.error;
            status.hidden = false;
        }
        Log.debug('Parce: conversation load failed', error);
    }
};

const search = async(query) => {
    activeQuery = query.trim();
    if (!activeQuery) {
        await loadContexts();
        return;
    }
    const request = ++navigationRequest;
    resetDetail();
    showListStatus(strings.loading);
    await showLimit(region('list-limit'), false, 0);
    region('contexts').replaceChildren();
    try {
        const response = await Ajax.call([{
            methodname: 'local_parce_search_history',
            args: {query: activeQuery, chatid: config.mode === 'admin' ? config.chatid : 0,
                userid: config.mode === 'admin' ? config.userid : 0, mode: config.mode},
        }])[0];
        const contexts = response.contexts.map((item) => contextData(item, true));
        const replaced = await replace(region('contexts'), {contexts}, () => request === navigationRequest);
        if (!replaced) {
            return;
        }
        region('contexts').querySelectorAll('.local-parce-history-context').forEach((node) => {
            node.dataset.loaded = 'true';
        });
        showListStatus(contexts.length ? '' : strings.noresults);
        await showLimit(region('list-limit'), response.limited, response.resultlimit,
            () => request === navigationRequest);
    } catch (error) {
        if (request === navigationRequest) {
            showListStatus(strings.error);
        }
        Log.debug('Parce: history search failed', error);
    }
};

const resize = () => {
    const available = Math.max(0, window.innerHeight - root().getBoundingClientRect().top - 16);
    root().style.height = `${available}px`;
};

const loadStrings = () => getStrings([
    {key: 'historyturns', component: 'local_parce'},
    {key: 'historyprompttokens', component: 'local_parce'},
    {key: 'historycompletiontokens', component: 'local_parce'},
    {key: 'historyloading', component: 'local_parce'},
    {key: 'historyempty', component: 'local_parce'},
    {key: 'historyerror', component: 'local_parce'},
    {key: 'historysearchnoresults', component: 'local_parce'},
]).then(([turns, prompttokens, completiontokens, loading, empty, error, noresults]) =>
    ({turns, prompttokens, completiontokens, loading, empty, error, noresults}));

export const init = async(initialConfig) => {
    config = initialConfig;
    strings = await loadStrings();
    resize();
    window.addEventListener('resize', resize);
    root().addEventListener('click', async(event) => {
        const toggle = event.target.closest('[data-action="toggle-context"]');
        if (toggle) {
            const contextNode = toggle.closest('[data-chatid]');
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            contextNode.querySelector('[data-region="context-conversations"]').hidden = expanded;
            if (!expanded) {
                await loadConversations(contextNode);
            }
            return;
        }
        const conversation = event.target.closest('[data-action="select-conversation"]');
        if (conversation) {
            await selectConversation(conversation);
        }
    });
    region('search-form').addEventListener('submit', async(event) => {
        event.preventDefault();
        await search(event.currentTarget.elements.query.value);
    });
    await loadContexts();
};
