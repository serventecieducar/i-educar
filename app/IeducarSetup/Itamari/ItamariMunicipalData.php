<?php

namespace App\IeducarSetup\Itamari;

/**
 * Dados oficiais do município de Itamari/BA e cadastro das unidades escolares.
 *
 * Fonte das escolas: planilha municipal ([Google Sheets](https://docs.google.com/spreadsheets/d/1dKMSayWexzi_GTU6ET9s2TnQjn_RJGc3DVUgp3QIpUQ/edit?gid=0#gid=0)).
 *
 * Critérios avaliativos: o município não informou respostas explícitas às perguntas do formulário;
 * adotamos padrão usual em redes municipais (média 6,0, frequência mínima 75%, quatro bimestres,
 * recuperação ao fim de cada período, avaliação mista numérica e conceitual). Ajuste no seeder
 * municipal se a secretaria definir outro regime.
 */
final class ItamariMunicipalData
{
    public const SECRETARIA_OFICIAL = 'Secretaria Municipal de Educação, Cultura e Esportes';

    public const PREFEITO = 'Everton Borges Vasconcelos';

    public const SECRETARIO_EDUCACAO = 'Heracton Sandes Amparo';

    public const ATO_NOMEACAO_SECRETARIO = 'Decreto Municipal nº 03, de 06 de janeiro de 2026';

    /** Forma avaliativa adotada no seed até definição formal pela SME. */
    public const FORMA_AVALIATIVA = 'Mista (nota numérica e conceitual)';

    /** Critério de aprovação (referência para relatórios / documentação). */
    public const CRITERIO_APROVACAO = 'Média final mínima 6,0 e frequência mínima de 75% da carga horária';

    public const MEDIA_APROVACAO = 6.0;

    public const FREQUENCIA_MINIMA_PERCENTUAL = 75.0;

    public const PERIODOS_AVALIATIVOS = '4 bimestres';

    public const REGIME_RECUPERACAO = 'Recuperação paralela ao fim de cada período avaliativo';

    public const CIDADE = 'Itamari';

    public const UF = 'BA';

    public const CEP_PADRAO = '45455000';

    /**
     * Converte nome vindo da planilha (geralmente em maiúsculas) para formato legível na interface.
     */
    public static function nomeEscolaParaExibicao(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return $nome;
        }

        return mb_convert_case(mb_strtolower($nome, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Unidades escolares (INEP e identificação). Endereço e e-mail conforme planilha municipal.
     *
     * @return list<array{
     *     inep: int,
     *     nome: string,
     *     cep: string,
     *     logradouro: string,
     *     numero: string,
     *     bairro: string,
     *     zona_urbana: bool,
     *     email: ?string
     * }>
     */
    public static function escolas(): array
    {
        return [
            [
                'inep' => 29309026,
                'nome' => 'ESCOLA MUNICIPAL ANEXO POLIVALENTE',
                'cep' => '45455000',
                'logradouro' => 'Rua Djalma Bessa',
                'numero' => 'S/N',
                'bairro' => 'Alto da Independência',
                'zona_urbana' => true,
                'email' => 'sirodrigues12@hotmail.com',
            ],
            [
                'inep' => 29309077,
                'nome' => 'ESCOLA DIDIMO PEREIRA DE VASCONCELOS',
                'cep' => '45455000',
                'logradouro' => 'Rua Antônio Jacinto de Souza',
                'numero' => 'S/N',
                'bairro' => 'Centro',
                'zona_urbana' => true,
                'email' => 'giandra_andrade@hotmail.com',
            ],
            [
                'inep' => 29309093,
                'nome' => 'ESCOLA DE I GRAU JOSE MARTINS DA SILVA',
                'cep' => '45455000',
                'logradouro' => 'Rua Djalma Bessa',
                'numero' => 'S/N',
                'bairro' => 'Alto da Independência',
                'zona_urbana' => true,
                'email' => 'sb5272577@gmail.com',
            ],
            [
                'inep' => 29309140,
                'nome' => 'ESCOLA MUNICIPAL PEDRO AUGUSTO DA SILVA',
                'cep' => '45455000',
                'logradouro' => 'Povoado Alto dos Cai Nágua',
                'numero' => 'S/N',
                'bairro' => 'Zona Rural',
                'zona_urbana' => false,
                'email' => 'lueduc@hotmail.com',
            ],
            [
                'inep' => 29309190,
                'nome' => 'ESCOLA SANTA LUZIA',
                'cep' => '45455000',
                'logradouro' => 'Fazenda Barra das Tabocas',
                'numero' => 'S/N',
                'bairro' => 'Zona Rural',
                'zona_urbana' => false,
                'email' => 'gilmaramr@gmail.com',
            ],
            [
                'inep' => 29309220,
                'nome' => 'ESCOLA MUNICIPAL ARLETE MAGALHAES',
                'cep' => '45455000',
                'logradouro' => 'Povoado de Mineiro',
                'numero' => 'S/N',
                'bairro' => 'Zona Rural',
                'zona_urbana' => false,
                'email' => 'sislandiaamparo8@gmail.com',
            ],
            [
                'inep' => 29309255,
                'nome' => 'ESCOLA MUNICIPAL CARMEM SOUZA GALVAO',
                'cep' => '45455000',
                'logradouro' => 'Rua do Cruzeiro',
                'numero' => 'S/N',
                'bairro' => 'Alto do Cruzeiro',
                'zona_urbana' => true,
                'email' => 'jesus10teamo@gmail.com',
            ],
            [
                'inep' => 29309263,
                'nome' => 'COLEGIO MUNICIPAL DE 1 GRAU PROF ROBERTO SANTOS',
                'cep' => '45455000',
                'logradouro' => 'Av. Osvaldo de Andrade Galvão',
                'numero' => '997',
                'bairro' => 'Alto da Independência',
                'zona_urbana' => true,
                'email' => 'nara.bahia@hotmail.com',
            ],
            [
                'inep' => 29309301,
                'nome' => 'ESCOLA MUNICIPAL MINERVINO FRANCA',
                'cep' => '45455000',
                'logradouro' => 'Povoado de Vila França',
                'numero' => 'S/N',
                'bairro' => 'Zona Rural',
                'zona_urbana' => false,
                'email' => 'gilmaramr@gmail.com',
            ],
            [
                'inep' => 29309336,
                'nome' => 'ESCOLA MUNICIPAL PEDRA VIVA',
                'cep' => '45455000',
                'logradouro' => 'Rua Porto do Sol',
                'numero' => 'S/N',
                'bairro' => 'Pôr do Sol',
                'zona_urbana' => true,
                'email' => 'almeidacelma2011@hotmail.com',
            ],
            [
                'inep' => 29309360,
                'nome' => 'ESCOLA MUNICIPAL VASCO NETO',
                'cep' => '45455000',
                'logradouro' => 'Rua Vasco Neto',
                'numero' => 'S/N',
                'bairro' => 'Alto do Cruzeiro',
                'zona_urbana' => true,
                'email' => 'josildacgal@gmail.com',
            ],
            [
                'inep' => 29309379,
                'nome' => 'ESCOLA MUNICIPAL WALDEMAR PEREIRA LUZ',
                'cep' => '45455000',
                'logradouro' => 'Rua Djalma Bessa',
                'numero' => 'S/N',
                'bairro' => 'Alto da Independência',
                'zona_urbana' => true,
                'email' => 'paixaoroberleia@hotmail.com',
            ],
        ];
    }
}
