// Lista de países para o seletor de telefone. `iso` é usado para a bandeira
// (flagcdn), `dial` é o código de país armazenado em country_code.
// Brasil é o padrão.
export const countries = [
    { iso: 'br', name: 'Brasil',          dial: '55' },
    { iso: 'pt', name: 'Portugal',        dial: '351' },
    { iso: 'us', name: 'Estados Unidos',  dial: '1' },
    { iso: 'ar', name: 'Argentina',       dial: '54' },
    { iso: 'py', name: 'Paraguai',        dial: '595' },
    { iso: 'uy', name: 'Uruguai',         dial: '598' },
    { iso: 'cl', name: 'Chile',           dial: '56' },
    { iso: 'bo', name: 'Bolívia',         dial: '591' },
    { iso: 'co', name: 'Colômbia',        dial: '57' },
    { iso: 'pe', name: 'Peru',            dial: '51' },
    { iso: 'mx', name: 'México',          dial: '52' },
    { iso: 'es', name: 'Espanha',         dial: '34' },
    { iso: 'it', name: 'Itália',          dial: '39' },
    { iso: 'fr', name: 'França',          dial: '33' },
    { iso: 'de', name: 'Alemanha',        dial: '49' },
    { iso: 'gb', name: 'Reino Unido',     dial: '44' },
    { iso: 'jp', name: 'Japão',           dial: '81' },
    { iso: 'cn', name: 'China',           dial: '86' },
];

export const defaultCountry = countries[0];

export function flagUrl(iso) {
    return `https://flagcdn.com/w40/${iso}.png`;
}

// Resolve o país a partir do código discado. Quando há ambiguidade (vários
// países com o mesmo dial), retorna o primeiro — para o foco no Brasil, o
// 55 sempre resolve para Brasil.
export function countryByDial(dial) {
    return countries.find((c) => c.dial === String(dial)) ?? defaultCountry;
}
