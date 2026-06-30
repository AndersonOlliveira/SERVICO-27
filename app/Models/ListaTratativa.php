<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;
use DateTime;
use App\Core\Functions;
use App\Core\AppManipularError;
use Override;

class ListaTratativa extends Model
{

    protected $funciton;
    protected $manipulador;
    ##[Override]
    public function __construct()
    {
        $this->funciton  = new Functions();
        $this->manipulador = new AppManipularError(__DIR__ . '/../error/error.txt');
        return parent::__construct();
    }


    public function lista_mobilidades()
    {
        $sql = "SELECT * FROM mobilidade";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getRelatorio_origim_list($idCobranca = null, $mes = null, $data_inicio = null, $data_fim = null)
    {


        $sql = "SELECT  
                crcid as n_nro,
                crcdatvct as Vencimento,
                crcdocger as Doc_Ger,crcvlr as valor,
                clicobtel as telefone,
                clicomctt as Contato_financeiro,
                upper(clissp) as Suspenso,
                clinomraz as cliente,
                cliid,
                array_to_string(array_agg(cast(venean as text)),', ') as vendedor,
                perfilcobtipo, 
                crcprepago FROM 
                cli INNER JOIN crc ON crccli = cliid 
                LEFT JOIN vencli ON vencli.venclicli = cli.cliid
                LEFT JOIN ven ON vencli.vencliven = ven.venid 
                LEFT JOIN perfilcob ON perfilcobid = cliperfilcobid
                where 
                crcfil =  1 and
                upper(crcbxd) = 'N' AND
                crcvlr > '0.00' AND
                crcprepago = false ";



        $filtros = [];
        $params = [];

        if (!empty($data_inicio) && !empty($data_fim)) {

            $filtros[] = " crc.crcdatvct::date BETWEEN :data_inicio AND :data_fim";
            $data_inicio_obj = $this->funciton->converterData($data_inicio);
            $data_fim_obj =  $this->funciton->converterData($data_fim);

            if ($data_inicio_obj && $data_fim_obj) {
                $data_inicio = $data_inicio_obj->format('Y-m-d');
                $data_fim = $data_fim_obj->format('Y-m-d');

                $params[':data_inicio'] = $data_inicio;
                $params[':data_fim'] = $data_fim;
            } else {

                return ['msg' => 'DATAS ENVIADAS NO FORMATO INVALIDO.'];
            }
        }
        // Cenário B: Filtro por Mês/Ano (Caso não tenha o período completo)
        elseif (!empty($mes)) {
            $dados_mes = explode('/', $mes);
            if (count($dados_mes) == 2) {

                $filtros[] = " EXTRACT(MONTH FROM crc.crcdatvct) = :mes";
                $filtros[] = " EXTRACT(YEAR FROM crc.crcdatvct) = :ano";
                $params[':mes'] = (int)$dados_mes[0];
                $params[':ano'] = (int)$dados_mes[1];
            }
        }
        // Se houver filtros, aplica-os à consulta
        if (!empty($filtros)) {
            $sql .= " AND " . implode(" AND ", $filtros);
        }

        if ($idCobranca) {
            $sql .= " AND crcid = :crcid";
            $params[':crcid'] = $idCobranca;
        }

        $sql .= "  group by crcid ,clicobtel,clicomctt,clissp,clinomraz,cliid,venean,perfilcobtipo,crcprepago
                ORDER BY vencimento asc;";

        try {
            $sql = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $sql->bindValue($key, $value);
            }
            $sql->execute();

            $result = [];
            while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
                $result[] = $row;
            }

            return $result;
        } catch (PDOException $e) {
            // $errorHandler = new AppManipularError('error');
            $this->manipulador->manipuladorDeErros(10, 'Erro na busca do getRelatorio_origim: ' . $e->getMessage(), __FILE__, __LINE__);
            echo "ERRO: " . $e->getMessage();
        }
    }

    public function lista_notification()
    {


        $sql = "";

        $sql = "SELECT  
            crc.crcid as N_Nro,               
            crc.crcdatvct as Vencimento,
            crc.crcdocger as Doc_Ger,
            crc.crcvlr as valor,
            crc.crcbancobrasil as nosso_numero_gopag, -- adicionado campo do nosso número para facilitar a identificação da cobrança
            SUBSTRING(crc.crcbarcode, 34, 7) as codigo_barras, -- adicionado campo do código de barras para facilitar a identificação da cobrança
            cli.clicobtel as telefone,
            cli.clicomctt as Contato_financeiro,
            upper(cli.clissp) as Suspenso,
            cli.clinomraz as cliente,
            cli.cliid,
            cli.clisspatm as supensao_automatica,
            array_to_string(array_agg(cast(ven.venean as text)),', ') as vendedor,
            perfilcob.perfilcobtipo, 
            crc.crcprepago,
            real_mov.crc_tratativas_crcid as movivementacao,
            real_mov.crc_tratativa_tipo_id as idTipo,
            real_mov.crc_tipo_acoes_id as idAcoes,
            real_mov.descricao_movimentacao as descricao_mov, 
            real_mov.ctr_interno as contratoResponsavel,
            tipo.tipo_tratativa as status,
            ac.acao_descricao as descricao_acao,             
            st.crc_tratativas_id as ultima_info,
            st.ultima_consulta,
            st.cod_status,
			st.p_contato
			
        FROM 
            cli cli 
        INNER JOIN crc crc ON crc.crccli = cli.cliid 
        LEFT JOIN vencli vencli ON vencli.venclicli = cli.cliid
        LEFT JOIN ven ven ON vencli.vencliven = ven.venid 
        LEFT JOIN perfilcob perfilcob ON perfilcob.perfilcobid = cli.cliperfilcobid
        INNER JOIN (
            SELECT *,
                ROW_NUMBER() OVER (PARTITION BY crc_tratativas_crcid ORDER BY id_crc_tratativas DESC) as rn_mov
            FROM public.crc_tratativas_movimentacao
        ) real_mov ON real_mov.crc_tratativas_crcid = crc.crcid AND real_mov.rn_mov = 1
        INNER JOIN public.crc_tratativa_tipo tipo ON tipo.id_crc_tratativa_tipo = real_mov.crc_tratativa_tipo_id
        INNER JOIN public.crc_tipo_acoes ac on ac.cod_acao = real_mov.crc_tipo_acoes_id
        INNER JOIN (
            SELECT 
                crc_tratativas_id,
                cod_status,
                data_cadastro as ultima_consulta,
				p_proximo_contato AS p_contato,
                ROW_NUMBER() OVER (PARTITION BY crc_tratativas_id ORDER BY data_cadastro DESC ) as rn 
            FROM 
                public.crc_tratativas_status
        ) st ON st.crc_tratativas_id = real_mov.id_crc_tratativas AND st.rn = 1
         WHERE 
            crc.crcfil = 1 AND
            upper(crc.crcbxd) = 'N' AND
            crc.crcvlr > 0.00 AND
            crc.crcprepago = false
            AND st.p_contato IS NOT NULL
            GROUP BY 
                crc.crcid, crc.crcdatvct, crc.crcdocger, crc.crcvlr, crc.crcbancobrasil,crc.crcbarcode,cli.clicobtel,
                cli.clicomctt, cli.clissp, cli.clinomraz, cli.cliid, cli.clisspatm ,perfilcob.perfilcobtipo,
                crc.crcprepago,
                real_mov.crc_tratativas_crcid,
                real_mov.crc_tratativa_tipo_id,
                real_mov.crc_tipo_acoes_id,
                real_mov.descricao_movimentacao,
                real_mov.ctr_interno,
                tipo.tipo_tratativa,
                ac.acao_descricao,
                st.crc_tratativas_id,
                st.ultima_consulta,
                st.cod_status,
				st.p_contato
                ORDER BY 
				Vencimento DESC,
                st.crc_tratativas_id DESC";
        try {

            $constul = $this->db->prepare($sql);

            $constul->execute();

            if ($constul->rowCount() > 0) {
                $result = [];

                while ($items = $constul->fetch(PDO::FETCH_ASSOC)) {
                    // $items['perfilcobtipo'] = self::removerAcentos($items['perfilcobtipo']);


                    if ($items['n_nro'] == 700989 || $items['n_nro'] == 700988) {
                        $items['supensao_automatica'] = 'N';
                    }

                    if (isset($items['contratoresponsavel']) && !empty($items['contratoresponsavel'])) {
                        //PEGO O NOME DO USUARIOS RESPONSAVEL PELA INSERÇÃO DO DADO   
                        $items['res'] = self::info_responsavel($items['contratoresponsavel']);
                        $items['descricao_mov'] = $this->funciton->convertEncode($items['descricao_mov']);
                        $items['descricao_acao'] = $this->funciton->convertEncode($items['descricao_acao']);
                        $items['perfilcobtipo'] = $this->funciton->convertEncode($items['perfilcobtipo']);
                    } else {
                        $items['res'] = 'SISTEMA';
                    }

                    $result[] = $items;
                }

                return $result;
            } else {

                return ['msg' => 'Nenhum resultado encontrado para os filtros informados.'];
            }
        } catch (PDOException $e) {
            $this->manipulador->manipuladorDeErros(10, 'Erro na busca do relatori: ' . $e->getMessage(), __FILE__, __LINE__);
        }
    }
    public function info_responsavel($contrato)
    {


        $sql = "";
        $sql = "SELECT ctrapl FROM ctr where ctrid = :ctrid";


        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':ctrid', $contrato);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return trim($result['ctrapl']);
        } catch (PDOException $e) {
            $this->manipulador->manipuladorDeErros(8, 'Erro na busca do ctr: ' . $e->getMessage(), __FILE__, __LINE__);
        }
    }
}
