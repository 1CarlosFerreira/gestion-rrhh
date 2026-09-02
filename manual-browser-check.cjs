const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  page.on('pageerror', (error) => console.error('PAGEERROR', error.message));
  const base = 'http://localhost:8080';
  const login = async (email) => {
    await page.goto(`${base}/login`);
    await page.getByLabel('Correo electrónico').fill(email);
    await page.getByLabel('Contraseña').fill('password');
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await page.waitForLoadState('networkidle');
  };
  const firstOption = async (selector) => page.locator(selector).evaluate((select) => select.options[1]?.value);

  await login('jefatura@example.test');
  await page.goto(`${base}/reemplazos/crear`);
  if (await page.locator('select[name="unidad_servicio_id"]').count()) {
    await page.selectOption('select[name="unidad_servicio_id"]', await firstOption('select[name="unidad_servicio_id"]'));
  }
  await page.selectOption('select[name="tipo_reemplazo_id"]', await firstOption('select[name="tipo_reemplazo_id"]'));
  await page.selectOption('#funcionario_id', await firstOption('#funcionario_id'));
  await page.locator('#buscar_reemplazante').fill('a');
  await page.locator('#resultados_reemplazante button:visible').first().click();
  await page.locator('input[name="fecha_inicio_ausencia"]').fill('2027-01-01');
  await page.locator('input[name="fecha_termino_ausencia"]').fill('2027-01-31');
  await page.locator('input[name="usar_mismo_periodo"]').check();
  await page.waitForTimeout(200);
  const copiedStart = await page.locator('input[name="fecha_inicio"]').inputValue();
  const copiedEnd = await page.locator('input[name="fecha_termino"]').inputValue();
  if (copiedStart !== '2027-01-01' || copiedEnd !== '2027-01-31') throw new Error(`El checkbox no copió ambos periodos: ${copiedStart}/${copiedEnd}`);
  await page.locator('input[name="usar_mismo_periodo"]').uncheck();
  await page.locator('input[name="fecha_inicio"]').fill('2027-01-05');
  await page.locator('input[name="fecha_termino"]').fill('2027-01-15');
  await page.selectOption('#estamento_id', await firstOption('#estamento_id'));
  await page.locator('#cargo_texto').fill('Cobertura navegador ficticia');
  await page.locator('textarea[name="justificacion"]').fill('Ausencia ficticia para prueba manual de navegador.');
  await page.getByRole('button', { name: 'Guardar borrador' }).click();
  await page.waitForURL(/\/reemplazos\/\d+\/editar/);
  const code = await page.locator('h2').filter({ hasText: 'Solicitud de Reemplazo' }).locator('xpath=following-sibling::p[1]').textContent();
  await page.getByRole('button', { name: 'Enviar a Gestión de Personas' }).click();
  await page.getByRole('button', { name: 'Sí, enviar solicitud' }).click();
  await page.waitForURL(/\/tramites\/\d+$/);
  const sent = await page.getByText('Enviada a Gestión de Personas', { exact: false }).count();
  console.log(JSON.stringify({ code: code.trim(), copiedStart, copiedEnd, sent: sent > 0, url: page.url() }));
  await browser.close();
})().catch((error) => { console.error(error); process.exit(1); });
