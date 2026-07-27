/* Base */

body,
body *:not(html):not(style):not(br):not(tr):not(code) {
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif,
        'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
    position: relative;
}

body {
    -webkit-text-size-adjust: none;
    background-color: #ffffff;
    color: #3f3f46;
    height: 100%;
    line-height: 1.4;
    margin: 0;
    padding: 0;
    width: 100% !important;
}

p,
ul,
ol,
blockquote {
    line-height: 1.45;
    text-align: start;
}

a {
    color: #18181b;
}

a img {
    border: none;
}

/* Typography */

h1 {
    color: #18181b;
    font-size: 17px;
    font-weight: bold;
    margin-top: 0;
    margin-bottom: 12px;
    text-align: start;
}

h2 {
    color: #18181b;
    font-size: 15px;
    font-weight: bold;
    margin-top: 20px;
    margin-bottom: 8px;
    text-align: start;
}

h3 {
    color: #18181b;
    font-size: 14px;
    font-weight: bold;
    margin-top: 0;
    margin-bottom: 4px;
    text-align: left;
}

p {
    font-size: 14px;
    line-height: 1.5em;
    margin-top: 0;
    margin-bottom: 10px;
    text-align: left;
}

p.sub {
    font-size: 12px;
}

img {
    max-width: 100%;
}

/* Layout */

.wrapper {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: #f4f4f5;
    margin: 0;
    padding: 0;
    width: 100%;
}

.content {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 0;
    padding: 0;
    width: 100%;
}

/* Header */

.header {
    padding: 18px 0 10px;
    text-align: center;
}

.header a {
    color: #18181b;
    font-size: 16px;
    font-weight: bold;
    text-decoration: none;
}

/* Body */

.body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: #f4f4f5;
    border-bottom: 1px solid #f4f4f5;
    border-top: 1px solid #f4f4f5;
    margin: 0;
    padding: 0;
    width: 100%;
}

.inner-body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 600px;
    background-color: #ffffff;
    border-color: #e4e4e7;
    border-radius: 6px;
    border-width: 1px;
    margin: 0 auto;
    padding: 0;
    width: 600px;
}

.inner-body a {
    word-break: break-word;
}

.content-cell {
    max-width: 100vw;
    padding: 20px;
}

/* Subcopy */

.subcopy {
    border-top: 1px solid #e4e4e7;
    margin-top: 18px;
    padding-top: 14px;
}

.subcopy p {
    font-size: 12px;
    color: #71717a;
}

/* Footer */

.footer {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 600px;
    margin: 0 auto;
    padding: 0;
    text-align: center;
    width: 600px;
}

.footer p {
    color: #a1a1aa;
    font-size: 11px;
    text-align: center;
}

.footer a {
    color: #a1a1aa;
    text-decoration: underline;
}

/* Tables */

.table table {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 14px auto;
    width: 100%;
}

.table th {
    border-bottom: 1px solid #e4e4e7;
    margin: 0;
    padding-bottom: 6px;
    font-size: 12px;
    color: #71717a;
}

.table td {
    color: #3f3f46;
    font-size: 13px;
    line-height: 17px;
    margin: 0;
    padding: 6px 0;
}

/* Buttons */

.action {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 18px auto;
    padding: 0;
    text-align: center;
    width: 100%;
    float: unset;
}

.button {
    -webkit-text-size-adjust: none;
    border-radius: 4px;
    color: #fff;
    display: inline-block;
    overflow: hidden;
    text-decoration: none;
}

.button-primary {
    background-color: #18181b;
    border-bottom: 8px solid #18181b;
    border-left: 18px solid #18181b;
    border-right: 18px solid #18181b;
    border-top: 8px solid #18181b;
}

/* Panels */

.panel {
    border-left: #d4d4d8 solid 4px;
    margin: 12px 0;
}

.panel-content {
    background-color: #fafafa;
    color: #3f3f46;
    padding: 12px;
}

.panel-content p {
    color: #3f3f46;
}

.panel-item {
    padding: 0;
}

.panel-item p:last-of-type {
    margin-bottom: 0;
    padding-bottom: 0;
}

/* Header */

.audit-title {
    font-size: 20px;
    font-weight: bold;
    color: #18181b;
    margin: 0 0 2px 0;
    letter-spacing: -0.01em;
}

.audit-period {
    font-size: 12px;
    color: #71717a;
    margin: 0 0 16px 0;
}

/* Summary */

.audit-summary {
    width: 100%;
    border-collapse: collapse;
    border-radius: 5px;
    margin: 0 0 5px 0;
}

.audit-summary td {
    padding: 9px 12px;
    vertical-align: middle;
}

.audit-summary-count {
    font-size: 20px;
    font-weight: 600;
    line-height: 1.1;
    white-space: nowrap;
    padding-right: 8px;
}

.audit-summary-label {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding-left: 0;
    width: 100%;
}

.audit-summary-meta {
    font-size: 12px;
    color: #52525b;
    white-space: nowrap;
}

.audit-summary-delta {
    font-size: 12px;
    color: #52525b;
    white-space: nowrap;
    padding-left: 8px;
}

.audit-summary-footnote {
    font-size: 11px;
    color: #a1a1aa;
    margin: 6px 0 16px 0;
}

/* Chart */

.audit-chart {
    width: 100%;
    border-collapse: collapse;
    margin: 0 0 16px 0;
}

/* Issue cards */

.audit-card {
    width: 100%;
    border-collapse: separate;
    border: 1px solid #e4e4e7;
    border-radius: 6px;
    margin: 0 0 10px 0;
    background-color: #ffffff;
}

.audit-card-head {
    padding: 12px 14px 0 14px;
}

.audit-card-badges {
    vertical-align: top;
}

.audit-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    padding: 3px 7px;
    border-radius: 3px;
    white-space: nowrap;
}

.audit-badge-prio {
    border: 1px solid #ddd6fe;
    font-weight: 600;
}

.audit-badge-new {
    background-color: #ffffff;
    border: 1px solid #e4e4e7;
    color: #52525b;
}

.audit-badge-queue {
    background-color: #eef2ff;
    border: 1px solid #c7d2fe;
    color: #4338ca;
}

.audit-channel-divider {
    margin: 32px 0 12px;
    background-color: #3f3f46;
    border-radius: 6px;
}

.audit-channel-divider-queue {
    background-color: #4338ca;
}

.audit-channel-divider-label {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.1em;
    color: #ffffff;
    padding: 10px 14px;
    white-space: nowrap;
}

.audit-channel-divider-hint {
    font-weight: 500;
    letter-spacing: normal;
    color: #d4d4d8;
}

.audit-channel-divider-count {
    font-size: 12px;
    color: #d4d4d8;
    padding: 10px 14px;
    white-space: nowrap;
}

.audit-card-figure {
    vertical-align: top;
    white-space: nowrap;
}

.audit-card-count {
    font-size: 22px;
    font-weight: 700;
    line-height: 1;
    color: #18181b;
    vertical-align: middle;
    white-space: nowrap;
}

.audit-card-count-mark {
    font-weight: 700;
    color: #a1a1aa;
    margin-right: 1px;
}


.audit-card-title {
    font-size: 16px;
    font-weight: 600;
    line-height: 1.3;
    color: #18181b;
    margin: 10px 0 2px 0;
}

.audit-card-origin {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    color: #71717a;
    margin: 0 0 12px 0;
    word-break: break-word;
}

.audit-card-detail {
    padding: 12px 14px;
    border-top: 1px solid #f1f1f3;
}

.audit-card-key {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #a1a1aa;
    margin: 0 0 2px;
}

.audit-card-value {
    font-size: 13px;
    line-height: 1.5;
    color: #3f3f46;
    margin: 0 0 10px;
}

.audit-card-value-last {
    margin-bottom: 0;
}

.audit-card-unanalysed {
    font-size: 12px;
    font-style: italic;
    line-height: 1.5;
    color: #a1a1aa;
    margin: 0;
}

.audit-card-foot {
    padding: 9px 14px;
    background-color: #fafafa;
    border-top: 1px solid #f1f1f3;
    border-radius: 0 0 5px 5px;
}

.audit-card-foot-meta {
    font-size: 11px;
    color: #a1a1aa;
}

.audit-foot-time {
    font-size: 12px;
    font-weight: 600;
    color: #3f3f46;
}

.audit-card-foot-channel {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #a1a1aa;
    white-space: nowrap;
}

.audit-card-channel {
    display: inline-block;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 11px;
    font-weight: 400;
    letter-spacing: 0;
    color: #52525b;
    background-color: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 3px;
    padding: 1px 6px;
    margin-left: 2px;
}

/* Utilities */

.break-all {
    word-break: break-all;
}
