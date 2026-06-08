#import <Foundation/Foundation.h>

// A simple greeter class
@interface Greeter : NSObject
@property (nonatomic, copy) NSString *name;
- (NSString *)greet;
@end

@implementation Greeter
- (NSString *)greet {
    return [NSString stringWithFormat:@"Hello, %@!", self.name];
}
@end

int main(int argc, const char *argv[]) {
    @autoreleasepool {
        Greeter *g = [[Greeter alloc] init];
        g.name = @"Objective-C";
        NSArray *nums = @[@1, @2, @3];
        NSLog(@"%@ count=%lu", [g greet], (unsigned long)nums.count);
    }
    return 0;
}
