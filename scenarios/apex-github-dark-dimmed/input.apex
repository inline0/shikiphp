public class AccountService {
    @AuraEnabled
    public static List<Account> getAccounts(Integer limitSize) {
        List<Account> accounts = [
            SELECT Id, Name, Industry
            FROM Account
            WHERE Industry != null
            LIMIT :limitSize
        ];
        for (Account acc : accounts) {
            System.debug('Account: ' + acc.Name);
        }
        return accounts;
    }
}
