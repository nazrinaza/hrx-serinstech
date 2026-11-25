# Serinstech HR — Brand & Colour Tokens

Source: colour palette supplied by the client (25 Nov 2026) + logos in `/logo`.

## Palette

| Token | Hex | RGB | Role |
|---|---|---|---|
| `mist` | `#EAEFEF` | 234, 239, 239 | App background, table stripes, input fills |
| `slate` | `#BFC9D1` | 191, 201, 209 | Borders, dividers, disabled states, muted icons |
| `ink` | `#25343F` | 37, 52, 63 | Sidebar / top bar, headings, body text, dark-mode surface |
| `ember` | `#FF9B51` | 255, 155, 81 | Primary accent: primary buttons, active menu item, focus ring, highlights |
| `white` | `#FFFFFF` | — | Cards, modals, form panels |
| `logo-orange` | `#F26649` | 242, 102, 73 | **Logo only** — sampled from the logo flame; not a UI colour |

Status colours (not in the palette, needed for payroll/leave states) — keep muted so `ember` stays the only loud colour:
`success #2F855A` · `warning #B7791F` · `danger #C53030` · `info #2B6CB0`.

## Contrast rules (WCAG AA)

| Pair | Ratio | Use |
|---|---|---|
| `ink` on `mist` / white | ~12–13 : 1 | ✅ all text |
| `ink` on `ember` | ~6.2 : 1 | ✅ text on primary buttons |
| white on `ember` | ~2.1 : 1 | ❌ never — fails AA |
| white on `ink` | ~13 : 1 | ✅ sidebar text |
| `ember` on `ink` | ~6.2 : 1 | ✅ active menu item / icons in sidebar |
| `slate` on white | ~1.6 : 1 | ⚠️ borders only, never text |

## Logo usage

* Light surfaces (login card, payslip PDF, white header) → `Logo_Light_BG.png`.
* `ink` sidebar / dark mode → `Logo_Dark_BG.png` (white wordmark).
* Don't place the logo on `ember` — the coral flame (`#F26649`) clashes with the amber accent.

## Tailwind config (planned)

```js
// tailwind.config.js → theme.extend.colors
colors: {
  mist:  '#EAEFEF',
  slate: { DEFAULT: '#BFC9D1' },
  ink:   '#25343F',
  ember: { DEFAULT: '#FF9B51', 600: '#F2843A', 50: '#FFF3EA' },
}
```
`ember-600` = hover/pressed state, `ember-50` = selected-row / soft badge background.
