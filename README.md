# Kubee Load Testing Web App

이 프로젝트는 AWS 인프라 위에서 프로메테우스와 그라파나 모니터링 실습을 위해 만들어진 부하 테스트용 PHP 웹 애플리케이션입니다.

## 기능
- **Normal Mode:** 정상 응답 속도 확인
- **CPU Load:** SHA-256 해시 연산 반복을 통한 CPU 부하 유발
- **Memory Load:** 대용량 문자열 할당을 통한 메모리 점유
- **DB Load:** AWS RDS(MySQL)에 접속하여 Insert/Select 쿼리 반복 수행
- **Latency:** 2초 지연 응답 시뮬레이션
- **Error:** 500 Internal Server Error 강제 발생

## 배포 방식
이 코드는 Ansible에 의해 AWS EC2 인스턴스로 배포됩니다.
DB 접속 정보(`db_config.php`)는 리포지토리에 포함되지 않으며, 배포 시점에 Ansible이 동적으로 생성합니다.
